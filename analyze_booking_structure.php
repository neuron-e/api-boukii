<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "📋 ANALYZING TABLE STRUCTURES\n";
echo "============================\n\n";

echo "📝 'bookings' table columns:\n";
$bookings = Schema::getColumnListing('bookings');
foreach ($bookings as $column) {
    echo "  - $column\n";
}

echo "\n📝 'booking_users' table columns:\n";
$bookingUsers = Schema::getColumnListing('booking_users');
foreach ($bookingUsers as $column) {
    echo "  - $column\n";
}

echo "\n🔗 Sample data relationships:\n";
$sampleBooking = DB::table('bookings')->first();
if ($sampleBooking) {
    echo "Sample booking ID: {$sampleBooking->id}\n";
    foreach ((array)$sampleBooking as $key => $value) {
        if (str_contains($key, 'course') || str_contains($key, 'id')) {
            echo "  $key: $value\n";
        }
    }
}

echo "\n🔗 Sample booking_user:\n";
$sampleBookingUser = DB::table('booking_users')->first();
if ($sampleBookingUser) {
    echo "Sample booking_user ID: {$sampleBookingUser->id}\n";
    foreach ((array)$sampleBookingUser as $key => $value) {
        if (str_contains($key, 'course') || str_contains($key, 'booking') || str_contains($key, 'id')) {
            echo "  $key: $value\n";
        }
    }
}