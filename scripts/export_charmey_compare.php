<?php

declare(strict_types=1);

use App\Exports\CoursesBySeasonExport;
use App\Exports\CoursesBySeasonLegacyExport;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$timestamp = date('Ymd_His');
$dir = 'exports/charmey_compare_' . $timestamp;
Storage::disk('local')->makeDirectory($dir);

$currentFile = $dir . '/charmey_current_export.xlsx';
$legacyFile = $dir . '/charmey_legacy_export.xlsx';
$reportFile = $dir . '/comparison_report.json';
$reportMdFile = $dir . '/comparison_report.md';

$schoolId = 8;
$currentSeasonId = 4;
$legacyFrom = '2023-09-01';
$legacyTo = '2024-04-01';

// 1) Current export (boukii_pro)
Config::set('database.connections.mysql.database', 'boukii_pro');
DB::purge('mysql');
DB::reconnect('mysql');

Excel::store(
    new CoursesBySeasonExport($schoolId, $currentSeasonId, null, null, false),
    $currentFile,
    'local'
);

// 2) Legacy export (legacy dump db)
Config::set('database.connections.mysql.database', 'boukii_legacy_tmp');
DB::purge('mysql');
DB::reconnect('mysql');

Excel::store(
    new CoursesBySeasonLegacyExport($schoolId, $legacyFrom, $legacyTo, false),
    $legacyFile,
    'local'
);

// Restore default DB
Config::set('database.connections.mysql.database', 'boukii_pro');
DB::purge('mysql');
DB::reconnect('mysql');

$basePath = storage_path('app');
$currentPath = $basePath . DIRECTORY_SEPARATOR . $currentFile;
$legacyPath = $basePath . DIRECTORY_SEPARATOR . $legacyFile;

$extractWorkbookInfo = function (string $path): array {
    $spreadsheet = IOFactory::load($path);
    $sheets = [];

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        $highestCol = $sheet->getHighestColumn();
        $highestRow = $sheet->getHighestRow();
        $header = $sheet->rangeToArray("A1:{$highestCol}1", null, true, true, true);
        $headerRow = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $header[1] ?? []), static fn ($v) => $v !== ''));

        $sheets[] = [
            'title' => $sheet->getTitle(),
            'highest_row' => $highestRow,
            'highest_column' => $highestCol,
            'header_row' => $headerRow,
        ];
    }

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet);

    return [
        'sheet_count' => count($sheets),
        'sheets' => $sheets,
    ];
};

$currentInfo = $extractWorkbookInfo($currentPath);
$legacyInfo = $extractWorkbookInfo($legacyPath);

$currentFirstHeader = $currentInfo['sheets'][0]['header_row'] ?? [];
$legacyFirstHeader = $legacyInfo['sheets'][0]['header_row'] ?? [];

$report = [
    'generated_at' => date('c'),
    'school_id' => $schoolId,
    'current' => [
        'season_id' => $currentSeasonId,
        'db' => 'boukii_pro',
        'file' => $currentFile,
        'info' => $currentInfo,
    ],
    'legacy' => [
        'date_from' => $legacyFrom,
        'date_to' => $legacyTo,
        'db' => 'boukii_legacy_tmp',
        'file' => $legacyFile,
        'info' => $legacyInfo,
    ],
    'header_comparison_first_sheet' => [
        'current_header' => $currentFirstHeader,
        'legacy_header' => $legacyFirstHeader,
        'only_in_current' => array_values(array_diff($currentFirstHeader, $legacyFirstHeader)),
        'only_in_legacy' => array_values(array_diff($legacyFirstHeader, $currentFirstHeader)),
        'common' => array_values(array_intersect($currentFirstHeader, $legacyFirstHeader)),
    ],
];

Storage::disk('local')->put($reportFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$md = [];
$md[] = '# Charmey export comparison';
$md[] = '';
$md[] = '- Generated: ' . $report['generated_at'];
$md[] = '- Current file: `' . $currentFile . '`';
$md[] = '- Legacy file: `' . $legacyFile . '`';
$md[] = '';
$md[] = '## Current export';
$md[] = '- Sheets: ' . $currentInfo['sheet_count'];
foreach ($currentInfo['sheets'] as $s) {
    $md[] = '  - ' . $s['title'] . ' (rows: ' . $s['highest_row'] . ', cols: ' . $s['highest_column'] . ')';
}
$md[] = '';
$md[] = '## Legacy export';
$md[] = '- Sheets: ' . $legacyInfo['sheet_count'];
foreach ($legacyInfo['sheets'] as $s) {
    $md[] = '  - ' . $s['title'] . ' (rows: ' . $s['highest_row'] . ', cols: ' . $s['highest_column'] . ')';
}
$md[] = '';
$md[] = '## Header diff (first sheet)';
$md[] = '- Common: ' . count($report['header_comparison_first_sheet']['common']);
$md[] = '- Only in current: ' . count($report['header_comparison_first_sheet']['only_in_current']);
$md[] = '- Only in legacy: ' . count($report['header_comparison_first_sheet']['only_in_legacy']);
$md[] = '';
$md[] = '### Only in current';
foreach ($report['header_comparison_first_sheet']['only_in_current'] as $h) {
    $md[] = '- ' . $h;
}
$md[] = '';
$md[] = '### Only in legacy';
foreach ($report['header_comparison_first_sheet']['only_in_legacy'] as $h) {
    $md[] = '- ' . $h;
}

Storage::disk('local')->put($reportMdFile, implode(PHP_EOL, $md));

echo "Generated:" . PHP_EOL;
echo " - " . $currentPath . PHP_EOL;
echo " - " . $legacyPath . PHP_EOL;
echo " - " . $basePath . DIRECTORY_SEPARATOR . $reportFile . PHP_EOL;
echo " - " . $basePath . DIRECTORY_SEPARATOR . $reportMdFile . PHP_EOL;
