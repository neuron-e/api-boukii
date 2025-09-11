<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "📝 Structure of 'courses' table:\n";
$columns = Schema::getColumnListing('courses');
foreach ($columns as $column) {
    echo "  - $column\n";
}

echo "\n🔍 Sample course data:\n";
$sample = DB::table('courses')->first();
if ($sample) {
    foreach ((array)$sample as $key => $value) {
        if (str_contains($key, 'season') || str_contains($key, 'school') || str_contains($key, 'id') || str_contains($key, 'name')) {
            echo "  $key: $value\n";
        }
    }
}