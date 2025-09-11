<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "📝 Structure of 'course_dates' table:\n";
$columns = Schema::getColumnListing('course_dates');
foreach ($columns as $column) {
    echo "  - $column\n";
}

echo "\n🔍 Sample course_date data:\n";
$sample = DB::table('course_dates')->first();
if ($sample) {
    foreach ((array)$sample as $key => $value) {
        if (str_contains($key, 'season') || str_contains($key, 'course') || str_contains($key, 'id') || str_contains($key, 'date')) {
            echo "  $key: $value\n";
        }
    }
}