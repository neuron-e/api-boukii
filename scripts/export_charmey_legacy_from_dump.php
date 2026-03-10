<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if ($argc < 3) {
    fwrite(STDERR, "Usage: php scripts/export_charmey_legacy_from_dump.php <input.sql> <output.xlsx> [YYYY-MM-DD] [YYYY-MM-DD]\n");
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

function toDisplayDate(?string $date): string
{
    if (!$date) {
        return '';
    }
    try {
        return (new DateTimeImmutable(substr($date, 0, 10)))->format('Y-m-d');
    } catch (Throwable $e) {
        return (string)$date;
    }
}

function toDisplaySchedule(?string $hour, ?string $duration): string
{
    $hour = trim((string)$hour);
    if ($hour === '') {
        return '';
    }
    $start = DateTimeImmutable::createFromFormat('H:i', $hour)
        ?: DateTimeImmutable::createFromFormat('H', $hour);
    if (!$start) {
        return $hour;
    }

    $minutes = 0;
    $duration = trim((string)$duration);
    if ($duration !== '') {
        if (is_numeric($duration)) {
            $minutes = ((int)$duration <= 12) ? ((int)$duration * 60) : (int)$duration;
        } elseif (preg_match('/(\d+)\s*h/i', $duration, $m)) {
            $minutes += ((int)$m[1]) * 60;
        } elseif (preg_match('/(\d+)\s*m/i', $duration, $m)) {
            $minutes += (int)$m[1];
        }
    }

    if ($minutes <= 0) {
        return $start->format('H:i');
    }
    $end = $start->modify('+' . $minutes . ' minutes');
    return $start->format('H:i') . ' - ' . $end->format('H:i');
}

fwrite(STDOUT, "Parsing legacy dump tables...\n");
$schools2 = parseTable($sql, 'schools2');
$courses2 = parseTable($sql, 'courses2');
$courses = parseTable($sql, 'courses');
$bookings2 = parseTable($sql, 'bookings2');
$bookingUsers2 = parseTable($sql, 'booking_users2');

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
fwrite(STDOUT, "Charmey school IDs: " . implode(',', $charmeyIds) . PHP_EOL);

$courseById = [];
foreach ($courses2 as $c) {
    if (($c['deleted_at'] ?? null) !== null) {
        continue;
    }
    if (!in_array((int)($c['school_id'] ?? 0), $charmeyIds, true)) {
        continue;
    }
    $courseById[(int)$c['id']] = $c;
}
foreach ($courses as $c) {
    if (($c['deleted_at'] ?? null) !== null) {
        continue;
    }
    if (!in_array((int)($c['school_id'] ?? 0), $charmeyIds, true)) {
        continue;
    }
    $cid = (int)$c['id'];
    if (!isset($courseById[$cid])) {
        $courseById[$cid] = $c;
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

$rowsByCourse = [];
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
    if ($courseId <= 0) {
        continue;
    }
    $course = $courseById[$courseId] ?? null;

    $date = (string)($bu['date'] ?? '');
    if ($date === '') {
        continue;
    }
    $justDate = substr($date, 0, 10);
    if ($justDate < $dateFrom || $justDate > $dateTo) {
        continue;
    }

    $courseName = (string)($course['name'] ?? ('Course #' . $courseId));
    $rowsByCourse[$courseName][] = [
        'Course' => $courseName,
        'Date' => toDisplayDate($justDate),
        'Schedule' => toDisplaySchedule((string)($bu['hour'] ?? ''), (string)($bu['duration'] ?? '')),
        'Group (Level)' => '',
        'Subgroup' => (string)($bu['course_groups_subgroup2_id'] ?? ''),
        'Monitor' => (string)($bu['monitor_id'] ?? ''),
        'Student Name' => 'User #' . (string)($bu['user_id'] ?? ''),
        'Age' => '',
        'Contact' => '',
        'Payment Status' => ((int)($booking['paid'] ?? 0) === 1 ? 'Paid' : 'Pending'),
        'Ordered Package' => (string)($booking['payrexx_reference'] ?? ('Booking #' . $bookingId)),
        'Email' => '',
    ];
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

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

ksort($rowsByCourse, SORT_NATURAL | SORT_FLAG_CASE);
$sheetCount = 0;

foreach ($rowsByCourse as $courseName => $rows) {
    usort($rows, static function (array $a, array $b): int {
        return strcmp(($a['Date'] ?? '') . ' ' . ($a['Schedule'] ?? ''), ($b['Date'] ?? '') . ' ' . ($b['Schedule'] ?? ''));
    });

    $sheet = $spreadsheet->createSheet();
    $title = mb_substr(preg_replace('/[\\[\\]\\*\\?\\:\\/\\\\]/', ' ', $courseName), 0, 31);
    $sheet->setTitle($title !== '' ? $title : ('Course ' . (++$sheetCount)));

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

if (count($spreadsheet->getAllSheets()) === 0) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('Courses');
    $sheet->setCellValue('A1', 'No data in selected range');
}

$writer = new Xlsx($spreadsheet);
$writer->save($outputFile);

fwrite(STDOUT, "Export generated: {$outputFile}\n");
fwrite(STDOUT, "Courses exported: " . count($rowsByCourse) . PHP_EOL);
