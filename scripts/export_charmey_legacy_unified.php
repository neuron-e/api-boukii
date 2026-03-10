<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($argc < 4) {
    fwrite(
        STDERR,
        "Usage: php scripts/export_charmey_legacy_unified.php <bookings_dump.sql> <metadata_dump.sql> <output.xlsx> [YYYY-MM-DD] [YYYY-MM-DD]\n"
    );
    exit(1);
}

$bookingsDump = $argv[1];
$metadataDump = $argv[2];
$outputFile = $argv[3];
$dateFrom = $argv[4] ?? '2023-09-01';
$dateTo = $argv[5] ?? '2024-04-01';

if (!is_file($bookingsDump)) {
    fwrite(STDERR, "Bookings dump not found: {$bookingsDump}\n");
    exit(1);
}
if (!is_file($metadataDump)) {
    fwrite(STDERR, "Metadata dump not found: {$metadataDump}\n");
    exit(1);
}

$sqlBookings = file_get_contents($bookingsDump);
$sqlMetadata = file_get_contents($metadataDump);
if ($sqlBookings === false || $sqlMetadata === false) {
    fwrite(STDERR, "Cannot read one of the input dumps\n");
    exit(1);
}

function tupleRowsUnified(string $values): array
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

function tupleColsUnified(string $row): array
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

function valUnified(?string $raw): ?string
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

function parseTableUnified(string $sql, string $table): array
{
    $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '`\s*\((.*?)\)\s*VALUES\s*(.*?);/s';
    preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER);
    $rows = [];
    foreach ($matches as $m) {
        $columns = array_map(static fn($c) => trim(str_replace('`', '', $c)), explode(',', $m[1]));
        foreach (tupleRowsUnified($m[2]) as $rowSql) {
            $items = tupleColsUnified($rowSql);
            if (count($items) !== count($columns)) {
                continue;
            }
            $r = [];
            foreach ($columns as $i => $c) {
                $r[$c] = valUnified($items[$i]);
            }
            $rows[] = $r;
        }
    }
    return $rows;
}

function indexByIdUnified(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $id = (int)($r['id'] ?? 0);
        if ($id > 0 && !isset($out[$id])) {
            $out[$id] = $r;
        }
    }
    return $out;
}

function mergeByIdUnified(array $priorityRows, array $fallbackRows): array
{
    $a = indexByIdUnified($priorityRows);
    $b = indexByIdUnified($fallbackRows);
    $out = $b;
    foreach ($a as $id => $row) {
        $out[$id] = $row;
    }
    return $out;
}

function ageFromBirthDateUnified(?string $birthDate): string
{
    if (!$birthDate) {
        return '';
    }
    try {
        $dob = new DateTimeImmutable(substr($birthDate, 0, 10));
        $today = new DateTimeImmutable('today');
        return (string)$dob->diff($today)->y;
    } catch (Throwable $e) {
        return '';
    }
}

function scheduleUnified(?string $hour, ?string $duration): string
{
    $hour = trim((string)$hour);
    if ($hour === '') {
        return '';
    }
    $start = DateTimeImmutable::createFromFormat('H:i', $hour)
        ?: DateTimeImmutable::createFromFormat('H:i:s', $hour);
    if (!$start) {
        return $hour;
    }

    $mins = 0;
    $duration = trim((string)$duration);
    if ($duration !== '') {
        if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $duration, $m)) {
            $mins = ((int)$m[1] * 60) + (int)$m[2];
        } elseif (is_numeric($duration)) {
            $n = (int)$duration;
            $mins = ($n <= 12 ? $n * 60 : $n);
        } elseif (preg_match('/(\d+)\s*h/i', $duration, $m)) {
            $mins += ((int)$m[1]) * 60;
        } elseif (preg_match('/(\d+)\s*m/i', $duration, $m)) {
            $mins += (int)$m[1];
        }
    }

    if ($mins <= 0) {
        return $start->format('H:i');
    }
    $end = $start->modify('+' . $mins . ' minutes');
    return $start->format('H:i') . ' - ' . $end->format('H:i');
}

fwrite(STDOUT, "Parsing bookings dump...\n");
$schools2Bookings = parseTableUnified($sqlBookings, 'schools2');
$courses2Bookings = parseTableUnified($sqlBookings, 'courses2');
$coursesBookings = parseTableUnified($sqlBookings, 'courses');
$bookings2 = parseTableUnified($sqlBookings, 'bookings2');
$bookingUsers2 = parseTableUnified($sqlBookings, 'booking_users2');
$usersBookings = parseTableUnified($sqlBookings, 'users');

fwrite(STDOUT, "Parsing metadata dump...\n");
$schools2Meta = parseTableUnified($sqlMetadata, 'schools2');
$courses2Meta = parseTableUnified($sqlMetadata, 'courses2');
$coursesMeta = parseTableUnified($sqlMetadata, 'courses');
$courseDates2Meta = parseTableUnified($sqlMetadata, 'course_dates2');
$usersMeta = parseTableUnified($sqlMetadata, 'users');
$monitorsMeta = parseTableUnified($sqlMetadata, 'monitors');
$degreesMeta = parseTableUnified($sqlMetadata, 'degrees');
$courseGroupsMeta = parseTableUnified($sqlMetadata, 'course_groups2');
$courseSubgroupsMeta = parseTableUnified($sqlMetadata, 'course_groups_subgroups2');

$schools2 = mergeByIdUnified($schools2Bookings, $schools2Meta);
$courses2 = mergeByIdUnified($courses2Bookings, $courses2Meta);
$courses = mergeByIdUnified($coursesBookings, $coursesMeta);
$users = mergeByIdUnified($usersBookings, $usersMeta);

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

$degreeById = [];
foreach ($degreesMeta as $d) {
    $degreeById[(int)($d['id'] ?? 0)] = (string)($d['name'] ?? '');
}

$courseByIdAny = [];
foreach ([$courses2, $courses] as $courseSet) {
    foreach ($courseSet as $c) {
        $cid = (int)($c['id'] ?? 0);
        if ($cid > 0 && !isset($courseByIdAny[$cid])) {
            $courseByIdAny[$cid] = $c;
        }
    }
}

$courseById = [];
foreach ($courses2 as $c) {
    if (($c['deleted_at'] ?? null) !== null) {
        continue;
    }
    $cid = (int)$c['id'];
    if ($cid > 0) {
        $courseById[$cid] = $c;
    }
}
foreach ($courses as $c) {
    if (($c['deleted_at'] ?? null) !== null) {
        continue;
    }
    $cid = (int)$c['id'];
    if (!isset($courseById[$cid])) {
        $courseById[$cid] = $c;
    }
}

$courseDateToCourseId = [];
$courseDateMetaById = [];
foreach ($courseDates2Meta as $cd) {
    $dateId = (int)($cd['id'] ?? 0);
    $mappedCourseId = (int)($cd['course2_id'] ?? 0);
    if ($dateId > 0 && $mappedCourseId > 0) {
        $courseDateToCourseId[$dateId] = $mappedCourseId;
        $courseDateMetaById[$dateId] = $cd;
    }
}

$bookingById = [];
foreach ($bookings2 as $b) {
    if (($b['deleted_at'] ?? null) !== null) {
        continue;
    }
    if (!in_array((int)($b['school_id'] ?? 0), $charmeyIds, true)) {
        continue;
    }
    $bookingById[(int)$b['id']] = $b;
}

$usedCourseIds = [];
foreach ($bookingUsers2 as $bu) {
    if (($bu['deleted_at'] ?? null) !== null) {
        continue;
    }
    $bookingId = (int)($bu['booking2_id'] ?? 0);
    $booking = $bookingById[$bookingId] ?? null;
    if (!$booking) {
        continue;
    }
    $date = (string)($bu['date'] ?? '');
    if ($date === '') {
        continue;
    }
    $justDate = substr($date, 0, 10);
    if ($justDate < $dateFrom || $justDate > $dateTo) {
        continue;
    }
    $rawCourseRef = (int)($bu['course2_id'] ?? 0);
    $courseId = $rawCourseRef;
    if ($courseId > 0 && !isset($courseById[$courseId]) && isset($courseDateToCourseId[$courseId])) {
        $courseId = (int)$courseDateToCourseId[$courseId];
    }
    if ($courseId > 0) {
        $usedCourseIds[$courseId] = true;
    }
}

if (!empty($usedCourseIds)) {
    $courseById = array_intersect_key($courseById, $usedCourseIds);
}

$groupsByCourse = [];
foreach ($courseGroupsMeta as $g) {
    if (($g['deleted_at'] ?? null) !== null) {
        continue;
    }
    $courseId = (int)($g['course2_id'] ?? 0);
    if ($courseId <= 0) {
        continue;
    }
    $groupsByCourse[$courseId][] = $g;
}

$subgroupById = [];
foreach ($courseSubgroupsMeta as $sg) {
    if (($sg['deleted_at'] ?? null) !== null) {
        continue;
    }
    $subgroupById[(int)($sg['id'] ?? 0)] = $sg;
}

$monitorNameById = [];
foreach ($monitorsMeta as $m) {
    $mid = (int)($m['id'] ?? 0);
    if ($mid <= 0) {
        continue;
    }
    $uid = (int)($m['user_id'] ?? 0);
    $u = $users[$uid] ?? null;
    if ($u) {
        $monitorNameById[$mid] = trim(((string)($u['first_name'] ?? '')) . ' ' . ((string)($u['last_name'] ?? '')));
    }
}

$headers = [
    'Course',
    'Date',
    'Schedule',
    'Group (Level)',
    'Subgroup',
    'Monitor',
    'Student Name',
    'Age',
    'Contact',
    'Payment Status',
    'Ordered Package',
    'Email',
];

$rowsByCourse = [];
$coverage = [
    'total_rows' => 0,
    'with_exact_subgroup' => 0,
    'with_inferred_group' => 0,
    'without_group_data' => 0,
];

foreach ($bookingUsers2 as $bu) {
    if (($bu['deleted_at'] ?? null) !== null) {
        continue;
    }
    $bookingId = (int)($bu['booking2_id'] ?? 0);
    $booking = $bookingById[$bookingId] ?? null;
    if (!$booking) {
        continue;
    }

    $date = (string)($bu['date'] ?? '');
    if ($date === '') {
        continue;
    }
    $justDate = substr($date, 0, 10);
    if ($justDate < $dateFrom || $justDate > $dateTo) {
        continue;
    }

    $rawCourseRef = (int)($bu['course2_id'] ?? 0);
    $courseId = $rawCourseRef;
    if ($courseId > 0 && !isset($courseById[$courseId]) && isset($courseDateToCourseId[$courseId])) {
        $courseId = (int)$courseDateToCourseId[$courseId];
    }
    if ($courseId <= 0 || (!isset($courseById[$courseId]) && !isset($courseByIdAny[$courseId]))) {
        continue;
    }
    $course = $courseById[$courseId] ?? $courseByIdAny[$courseId];
    $baseCourseName = (string)($course['name'] ?? ('Course #' . $courseId));
    $courseName = $baseCourseName;
    if ($rawCourseRef > 0 && $rawCourseRef !== $courseId) {
        $rawDate = (string)($courseDateMetaById[$rawCourseRef]['date'] ?? '');
        $rawDate = $rawDate !== '' ? substr($rawDate, 0, 10) : '';
        if ($rawDate !== '') {
            $courseName = sprintf('%s (%s) [#%d]', $baseCourseName, $rawDate, $rawCourseRef);
        } else {
            $courseName = sprintf('%s (#%d)', $baseCourseName, $rawCourseRef);
        }
    }

    $student = $users[(int)($bu['user_id'] ?? 0)] ?? null;
    $studentName = trim(((string)($student['first_name'] ?? '')) . ' ' . ((string)($student['last_name'] ?? '')));
    if ($studentName === '') {
        $studentName = 'User #' . (string)($bu['user_id'] ?? '');
    }
    $contact = (string)($student['phone'] ?? $student['telephone'] ?? '');
    $email = (string)($student['email'] ?? '');
    $age = ageFromBirthDateUnified((string)($student['birth_date'] ?? ''));

    $groupLevel = '';
    $subgroupLabel = '';
    $sgId = (int)($bu['course_groups_subgroup2_id'] ?? 0);

    if ($sgId > 0 && isset($subgroupById[$sgId])) {
        $sg = $subgroupById[$sgId];
        $subgroupLabel = 'SG #' . $sgId;
        $gId = (int)($sg['course_group2_id'] ?? 0);
        if ($gId > 0) {
            foreach ($groupsByCourse[$courseId] ?? [] as $g) {
                if ((int)$g['id'] === $gId) {
                    $groupLevel = (string)($degreeById[(int)($g['degree_id'] ?? 0)] ?? ('Group #' . $gId));
                    break;
                }
            }
        }
        $coverage['with_exact_subgroup']++;
    } else {
        $groups = $groupsByCourse[$courseId] ?? [];
        if (count($groups) === 1) {
            $g = $groups[0];
            $groupLevel = (string)($degreeById[(int)($g['degree_id'] ?? 0)] ?? ('Group #' . (string)$g['id']));
            $coverage['with_inferred_group']++;
        } else {
            $coverage['without_group_data']++;
        }
    }

    $monitorId = (int)($bu['monitor_id'] ?? 0);
    $monitorName = '';
    if ($monitorId > 0) {
        $monitorName = $monitorNameById[$monitorId] ?? '';
        if ($monitorName === '' && isset($users[$monitorId])) {
            $mu = $users[$monitorId];
            $monitorName = trim(((string)($mu['first_name'] ?? '')) . ' ' . ((string)($mu['last_name'] ?? '')));
        }
        if ($monitorName === '') {
            $monitorName = 'Monitor #' . $monitorId;
        }
    }

    $rowsByCourse[$courseName][] = [
        'Course' => $courseName,
        'Date' => $justDate,
        'Schedule' => scheduleUnified((string)($bu['hour'] ?? ''), (string)($bu['duration'] ?? '')),
        'Group (Level)' => $groupLevel,
        'Subgroup' => $subgroupLabel,
        'Monitor' => $monitorName,
        'Student Name' => $studentName,
        'Age' => $age,
        'Contact' => $contact,
        'Payment Status' => ((int)($booking['paid'] ?? 0) === 1 ? 'Paid' : 'Pending'),
        'Ordered Package' => (string)($booking['payrexx_reference'] ?? ('Booking #' . $bookingId)),
        'Email' => $email,
    ];

    $coverage['total_rows']++;
}

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);
ksort($rowsByCourse, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($rowsByCourse as $courseName => $rows) {
    usort($rows, static function (array $a, array $b): int {
        return strcmp(($a['Date'] ?? '') . ' ' . ($a['Schedule'] ?? ''), ($b['Date'] ?? '') . ' ' . ($b['Schedule'] ?? ''));
    });

    $sheet = $spreadsheet->createSheet();
    $title = mb_substr(preg_replace('/[\\[\\]\\*\\?\\:\\/\\\\]/', ' ', $courseName), 0, 31);
    $sheet->setTitle($title !== '' ? $title : 'Course');

    foreach ($headers as $i => $h) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
    }

    $line = 2;
    foreach ($rows as $row) {
        foreach ($headers as $col => $h) {
            $sheet->setCellValueByColumnAndRow($col + 1, $line, (string)($row[$h] ?? ''));
        }
        $line++;
    }

    foreach (range(1, count($headers)) as $c) {
        $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
    }
}

$meta = $spreadsheet->createSheet(0);
$meta->setTitle('_COVERAGE');
$meta->setCellValue('A1', 'Bookings dump');
$meta->setCellValue('B1', $bookingsDump);
$meta->setCellValue('A2', 'Metadata dump');
$meta->setCellValue('B2', $metadataDump);
$meta->setCellValue('A3', 'Date range');
$meta->setCellValue('B3', $dateFrom . ' .. ' . $dateTo);
$meta->setCellValue('A5', 'Rows exported');
$meta->setCellValue('B5', (int)$coverage['total_rows']);
$meta->setCellValue('A6', 'Rows with exact subgroup');
$meta->setCellValue('B6', (int)$coverage['with_exact_subgroup']);
$meta->setCellValue('A7', 'Rows with inferred group');
$meta->setCellValue('B7', (int)$coverage['with_inferred_group']);
$meta->setCellValue('A8', 'Rows without group data');
$meta->setCellValue('B8', (int)$coverage['without_group_data']);
$meta->getColumnDimension('A')->setAutoSize(true);
$meta->getColumnDimension('B')->setAutoSize(true);

if (count($spreadsheet->getAllSheets()) === 1) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('Courses');
    $sheet->setCellValue('A1', 'No data in selected range');
}

$writer = new Xlsx($spreadsheet);
$writer->save($outputFile);

fwrite(STDOUT, "Unified export generated: {$outputFile}\n");
fwrite(STDOUT, "Courses exported: " . count($rowsByCourse) . PHP_EOL);
fwrite(STDOUT, "Rows exported: " . (int)$coverage['total_rows'] . PHP_EOL);
