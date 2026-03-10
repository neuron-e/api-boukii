<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/export_charmey_legacy_live_like.php <input.sql> <output.xlsx> [YYYY-MM-DD] [YYYY-MM-DD]\n");
    exit(1);
}

$inputFile = $argv[1];
$outputFile = $argv[2];
$dateFrom = $argv[3] ?? '2023-12-01';
$dateTo = $argv[4] ?? '2024-04-30';

if (!is_file($inputFile)) {
    fwrite(STDERR, "Input file not found: {$inputFile}\n");
    exit(1);
}

$sql = file_get_contents($inputFile);
if ($sql === false) {
    fwrite(STDERR, "Cannot read input file: {$inputFile}\n");
    exit(1);
}

function tupleRows(string $values): array
{
    $rows = [];
    $in = false;
    $esc = false;
    $depth = 0;
    $start = null;
    $len = strlen($values);
    for ($i = 0; $i < $len; $i++) {
        $ch = $values[$i];
        if ($in) {
            if ($esc) {
                $esc = false;
            } elseif ($ch === '\\') {
                $esc = true;
            } elseif ($ch === "'") {
                $in = false;
            }
            continue;
        }
        if ($ch === "'") {
            $in = true;
            continue;
        }
        if ($ch === '(') {
            if ($depth === 0) {
                $start = $i + 1;
            }
            $depth++;
            continue;
        }
        if ($ch === ')') {
            $depth--;
            if ($depth === 0 && $start !== null) {
                $rows[] = substr($values, $start, $i - $start);
                $start = null;
            }
        }
    }
    return $rows;
}

function tupleCols(string $row): array
{
    $cols = [];
    $buf = '';
    $in = false;
    $esc = false;
    $len = strlen($row);
    for ($i = 0; $i < $len; $i++) {
        $ch = $row[$i];
        if ($in) {
            $buf .= $ch;
            if ($esc) {
                $esc = false;
            } elseif ($ch === '\\') {
                $esc = true;
            } elseif ($ch === "'") {
                $in = false;
            }
            continue;
        }
        if ($ch === "'") {
            $in = true;
            $buf .= $ch;
            continue;
        }
        if ($ch === ',') {
            $cols[] = trim($buf);
            $buf = '';
            continue;
        }
        $buf .= $ch;
    }
    $cols[] = trim($buf);
    return $cols;
}

function val(?string $raw): ?string
{
    if ($raw === null) {
        return null;
    }
    $raw = trim($raw);
    if (strcasecmp($raw, 'NULL') === 0) {
        return null;
    }
    if (strlen($raw) >= 2 && $raw[0] === "'" && $raw[strlen($raw) - 1] === "'") {
        return stripcslashes(substr($raw, 1, -1));
    }
    return $raw;
}

function parseTable(string $sql, string $table): array
{
    $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '`\s*\((.*?)\)\s*VALUES\s*(.*?);/s';
    preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);
    $rows = [];
    foreach ($matches as $m) {
        $columns = array_map(static fn($c) => trim(str_replace('`', '', $c)), explode(',', $m[1]));
        foreach (tupleRows($m[2]) as $rowSql) {
            $items = tupleCols($rowSql);
            if (count($items) !== count($columns)) {
                continue;
            }
            $r = [];
            foreach ($columns as $i => $c) {
                $r[$c] = val($items[$i]);
            }
            $rows[] = $r;
        }
    }
    return $rows;
}

fwrite(STDOUT, "Parsing legacy tables...\n");
$schools2 = parseTable($sql, 'schools2');
$courses2 = parseTable($sql, 'courses2');
$courses = parseTable($sql, 'courses');
$courseDates2 = parseTable($sql, 'course_dates2');
$courseGroups2 = parseTable($sql, 'course_groups2');
$courseSubgroups2 = parseTable($sql, 'course_groups_subgroups2');
$bookings2 = parseTable($sql, 'bookings2');
$bookingUsers2 = parseTable($sql, 'booking_users2');
$courseTypes = parseTable($sql, 'course_types');
$sports = parseTable($sql, 'sports');
$stations = parseTable($sql, 'stations');
$degrees = parseTable($sql, 'degrees');

$charmeyIds = [];
foreach ($schools2 as $school) {
    $name = strtolower((string)($school['name'] ?? ''));
    if (str_contains($name, 'charmey')) {
        $charmeyIds[] = (int)$school['id'];
    }
}
if (empty($charmeyIds)) {
    $charmeyIds = [8];
}
$charmeyIds = array_values(array_unique($charmeyIds));

$courseTypeById = [];
foreach ($courseTypes as $ct) {
    $courseTypeById[(int)$ct['id']] = (string)($ct['name'] ?? '');
}
$sportById = [];
foreach ($sports as $s) {
    $sportById[(int)$s['id']] = (string)($s['name'] ?? '');
}
$stationById = [];
foreach ($stations as $s) {
    $stationById[(int)$s['id']] = (string)($s['name'] ?? '');
}
$degreeById = [];
foreach ($degrees as $d) {
    $degreeById[(int)$d['id']] = (string)($d['name'] ?? '');
}

$bookingById = [];
foreach ($bookings2 as $b) {
    if (!in_array((int)($b['school_id'] ?? 0), $charmeyIds, true)) {
        continue;
    }
    if (($b['deleted_at'] ?? null) !== null) {
        continue;
    }
    $bookingById[(int)$b['id']] = $b;
}

$courseRows = [];
foreach ($courses2 as $c) {
    if (!in_array((int)($c['school_id'] ?? 0), $charmeyIds, true)) {
        continue;
    }
    if (($c['deleted_at'] ?? null) !== null) {
        continue;
    }
    $courseRows[(int)$c['id']] = $c;
}

// Fallback for dumps where booking_users2.course2_id actually points to `courses.id`.
foreach ($courses as $c) {
    if (!in_array((int)($c['school_id'] ?? 0), $charmeyIds, true)) {
        continue;
    }
    if (($c['deleted_at'] ?? null) !== null) {
        continue;
    }
    $cid = (int)$c['id'];
    if (!isset($courseRows[$cid])) {
        $courseRows[$cid] = $c;
    }
}

// Group stats per course (same philosophy as live legacy export).
$groupStats = [];
foreach ($courseGroups2 as $g) {
    if (($g['deleted_at'] ?? null) !== null) {
        continue;
    }
    $cid = (int)($g['course2_id'] ?? 0);
    if (!isset($courseRows[$cid])) {
        continue;
    }
    if (!isset($groupStats[$cid])) {
        $groupStats[$cid] = [
            'group_ids' => [],
            'subgroup_ids' => [],
            'total_subgroup_capacity' => 0,
            'degree_ids' => [],
        ];
    }
    $groupStats[$cid]['group_ids'][(int)$g['id']] = true;
    if (($g['degree_id'] ?? null) !== null) {
        $groupStats[$cid]['degree_ids'][(int)$g['degree_id']] = true;
    }
}
foreach ($courseSubgroups2 as $sg) {
    if (($sg['deleted_at'] ?? null) !== null) {
        continue;
    }
    $gid = (int)($sg['course_group2_id'] ?? 0);
    $courseId = null;
    foreach ($courseGroups2 as $g) {
        if ((int)$g['id'] === $gid) {
            $courseId = (int)($g['course2_id'] ?? 0);
            break;
        }
    }
    if ($courseId === null || !isset($courseRows[$courseId])) {
        continue;
    }
    if (!isset($groupStats[$courseId])) {
        $groupStats[$courseId] = [
            'group_ids' => [],
            'subgroup_ids' => [],
            'total_subgroup_capacity' => 0,
            'degree_ids' => [],
        ];
    }
    $groupStats[$courseId]['subgroup_ids'][(int)$sg['id']] = true;
    $groupStats[$courseId]['total_subgroup_capacity'] += (int)($sg['max_participants'] ?? 0);
}

// Booking stats keyed by course/date/hour.
$bookingStats = [];
$dateHoursByCourse = [];
foreach ($bookingUsers2 as $bu) {
    if (($bu['deleted_at'] ?? null) !== null) {
        continue;
    }
    $bookingId = (int)($bu['booking2_id'] ?? 0);
    $booking = $bookingById[$bookingId] ?? null;
    if (!$booking) {
        continue;
    }
    $courseId = (int)($bu['course2_id'] ?? 0);
    if (!isset($courseRows[$courseId])) {
        continue;
    }
    $date = (string)($bu['date'] ?? '');
    $hour = (string)($bu['hour'] ?? '');
    if ($date === '') {
        continue;
    }
    if ($date < $dateFrom || $date > $dateTo) {
        continue;
    }
    $key = $courseId . '|' . $date . '|' . $hour;
    $dateHoursByCourse[$courseId][$date . '|' . $hour] = [
        'date' => $date,
        'hour' => $hour,
        'course_date_id' => null,
    ];
    if (!isset($bookingStats[$key])) {
        $bookingStats[$key] = [
            'booked_participants' => 0,
            'booked_amount' => 0.0,
            'paid_amount' => 0.0,
        ];
    }
    $linePrice = (float)($bu['price'] ?? 0);
    $bookingStats[$key]['booked_participants']++;
    $bookingStats[$key]['booked_amount'] += $linePrice;
    if ((int)($booking['paid'] ?? 0) === 1) {
        $bookingStats[$key]['paid_amount'] += $linePrice;
    }
}

// Course dates per course in range.
$datesByCourse = [];
foreach ($courseDates2 as $cd) {
    if (($cd['deleted_at'] ?? null) !== null) {
        continue;
    }
    $cid = (int)($cd['course2_id'] ?? 0);
    if (!isset($courseRows[$cid])) {
        continue;
    }
    $date = (string)($cd['date'] ?? '');
    if ($date === '' || $date < $dateFrom || $date > $dateTo) {
        continue;
    }
    $datesByCourse[$cid][] = $cd;
    $dateHoursByCourse[$cid][$date . '|' . (string)($cd['hour'] ?? '')] = [
        'date' => $date,
        'hour' => (string)($cd['hour'] ?? ''),
        'course_date_id' => $cd['id'] ?? null,
    ];
}

$headings = [
    'Course ID',
    'Course name',
    'Course type',
    'Course type ID',
    'Sport ID',
    'Sport name',
    'Station ID',
    'Station',
    'Price',
    'Max participants',
    'Duration',
    'Flexible duration',
    'Reservation start',
    'Reservation end',
    'Reservation day start',
    'Reservation day end',
    'Reservation hour min',
    'Reservation hour max',
    'Legacy group ID',
    'Confirm attendance',
    'Online',
    'Active',
    'Range start',
    'Range end',
    'Course date ID',
    'Date',
    'Hour',
    'Groups count',
    'Subgroups count',
    'Subgroup total capacity',
    'Degree IDs',
    'Degree names',
    'Booked participants',
    'Booked amount',
    'Paid amount',
];

$spreadsheet = new Spreadsheet();
$summary = $spreadsheet->getActiveSheet();
$summary->setTitle('Summary');
$summaryHeaders = ['Course', 'Course ID', 'Rows exported', 'Booked participants', 'Booked amount', 'Paid amount'];
foreach ($summaryHeaders as $i => $h) {
    $summary->setCellValueByColumnAndRow($i + 1, 1, $h);
}

$rowSummary = 2;
$sheetIndex = 1;
$courseIds = array_keys($courseRows);
sort($courseIds);

foreach ($courseIds as $courseId) {
    $course = $courseRows[$courseId];
    $courseName = (string)($course['name'] ?? ('Course ' . $courseId));
    $rows = $datesByCourse[$courseId] ?? [];
    if (empty($rows) && !empty($dateHoursByCourse[$courseId])) {
        foreach ($dateHoursByCourse[$courseId] as $dh) {
            $rows[] = [
                'id' => $dh['course_date_id'],
                'date' => $dh['date'],
                'hour' => $dh['hour'],
            ];
        }
    }
    if (empty($rows)) {
        continue;
    }
    usort($rows, static function ($a, $b) {
        return strcmp((string)$a['date'] . ' ' . (string)($a['hour'] ?? ''), (string)$b['date'] . ' ' . (string)($b['hour'] ?? ''));
    });

    $sheet = $spreadsheet->createSheet($sheetIndex++);
    $title = mb_substr(preg_replace('/[\\[\\]\\*\\?\\:\\/\\\\]/', ' ', $courseName), 0, 31);
    $sheet->setTitle($title ?: ('Course ' . $courseId));

    foreach ($headings as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }

    $groupsCount = isset($groupStats[$courseId]) ? count($groupStats[$courseId]['group_ids']) : 0;
    $subgroupsCount = isset($groupStats[$courseId]) ? count($groupStats[$courseId]['subgroup_ids']) : 0;
    $totalSubgroupCapacity = (int)($groupStats[$courseId]['total_subgroup_capacity'] ?? 0);
    $degreeIds = isset($groupStats[$courseId]) ? array_keys($groupStats[$courseId]['degree_ids']) : [];
    sort($degreeIds);
    $degreeNames = [];
    foreach ($degreeIds as $did) {
        $degreeNames[] = $degreeById[$did] ?? ('Degree ' . $did);
    }

    $outLine = 2;
    $sumParticipants = 0;
    $sumBooked = 0.0;
    $sumPaid = 0.0;
    foreach ($rows as $cd) {
        $date = (string)($cd['date'] ?? '');
        $hour = (string)($cd['hour'] ?? '');
        $key = $courseId . '|' . $date . '|' . $hour;
        $stats = $bookingStats[$key] ?? ['booked_participants' => 0, 'booked_amount' => 0.0, 'paid_amount' => 0.0];

        $values = [
            $courseId,
            $courseName,
            ((int)($course['course_supertype_id'] ?? 0) === 1
                ? 'collective'
                : (((int)($course['course_supertype_id'] ?? 0) === 2)
                    ? 'private'
                    : (((int)($course['course_type'] ?? 0) === 2 ? 'private' : (((int)($course['course_type'] ?? 0) === 1) ? 'collective' : 'unknown'))))),
            (string)($course['course_type_id'] ?? ''),
            (string)($course['sport_id'] ?? ''),
            $sportById[(int)($course['sport_id'] ?? 0)] ?? '',
            (string)($course['station_id'] ?? ''),
            $stationById[(int)($course['station_id'] ?? 0)] ?? '',
            (float)($course['price'] ?? 0),
            (string)($course['max_participants'] ?? ''),
            (string)($course['duration'] ?? ''),
            ((int)($course['duration_flexible'] ?? 0) === 1 ? 'yes' : 'no'),
            (string)($course['date_start_res'] ?? ''),
            (string)($course['date_end_res'] ?? ''),
            (string)($course['day_start_res'] ?? ''),
            (string)($course['day_end_res'] ?? ''),
            (string)($course['hour_min'] ?? ''),
            (string)($course['hour_max'] ?? ''),
            (string)($course['group_id'] ?? ''),
            ((int)($course['confirm_attendance'] ?? 0) === 1 ? 'yes' : 'no'),
            ((int)($course['online'] ?? 0) === 1 ? 'yes' : 'no'),
            ((int)($course['active'] ?? 0) === 1 ? 'yes' : 'no'),
            (string)($course['date_start'] ?? ''),
            (string)($course['date_end'] ?? ''),
            (string)($cd['id'] ?? ''),
            $date,
            $hour,
            $groupsCount,
            $subgroupsCount,
            $totalSubgroupCapacity,
            implode(',', $degreeIds),
            implode(', ', $degreeNames),
            (int)$stats['booked_participants'],
            round((float)$stats['booked_amount'], 2),
            round((float)$stats['paid_amount'], 2),
        ];

        foreach ($values as $col => $value) {
            $sheet->setCellValueByColumnAndRow($col + 1, $outLine, $value);
        }

        $sumParticipants += (int)$stats['booked_participants'];
        $sumBooked += (float)$stats['booked_amount'];
        $sumPaid += (float)$stats['paid_amount'];
        $outLine++;
    }

    foreach (range(1, count($headings)) as $col) {
        $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
    }

    $summary->setCellValueByColumnAndRow(1, $rowSummary, $courseName);
    $summary->setCellValueByColumnAndRow(2, $rowSummary, $courseId);
    $summary->setCellValueByColumnAndRow(3, $rowSummary, max(0, $outLine - 2));
    $summary->setCellValueByColumnAndRow(4, $rowSummary, $sumParticipants);
    $summary->setCellValueByColumnAndRow(5, $rowSummary, round($sumBooked, 2));
    $summary->setCellValueByColumnAndRow(6, $rowSummary, round($sumPaid, 2));
    $rowSummary++;
}

foreach (range(1, 6) as $c) {
    $summary->getColumnDimensionByColumn($c)->setAutoSize(true);
}

$writer = new Xlsx($spreadsheet);
$writer->save($outputFile);

fwrite(STDOUT, "Live-like legacy export generated: {$outputFile}\n");
