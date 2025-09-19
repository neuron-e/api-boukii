<?php

/**
 * Test script for the improved weather service
 * This script tests the downloadAccuweatherData() method with comprehensive error handling
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Station;
use Illuminate\Support\Facades\Log;

echo "=== Weather Service Test ===\n\n";

// Test 1: Get a station to test with
$station = Station::where('active', true)->first();

if (!$station) {
    echo "❌ No active stations found in database\n";
    exit(1);
}

echo "📍 Testing with station: {$station->name} (ID: {$station->id})\n";
echo "   Coordinates: {$station->latitude}, {$station->longitude}\n\n";

// Test 2: Test coordinate validation
echo "🔍 Testing coordinate validation...\n";

// Create a test station with invalid coordinates
$invalidStation = new Station();
$invalidStation->name = 'Test Station';
$invalidStation->latitude = '';
$invalidStation->longitude = '';

$method = new ReflectionMethod(Station::class, 'validateCoordinates');
$method->setAccessible(true);

if ($method->invoke($invalidStation)) {
    echo "❌ Coordinate validation failed - invalid coordinates were accepted\n";
} else {
    echo "✅ Coordinate validation working - invalid coordinates rejected\n";
}

// Test valid coordinates
$validStation = new Station();
$validStation->name = 'Test Station';
$validStation->latitude = '46.0';
$validStation->longitude = '8.0';

if ($method->invoke($validStation)) {
    echo "✅ Coordinate validation working - valid coordinates accepted\n";
} else {
    echo "❌ Coordinate validation failed - valid coordinates were rejected\n";
}

echo "\n";

// Test 3: Test the actual weather download
echo "🌤️  Testing weather data download...\n";

try {
    $result = $station->downloadAccuweatherData();

    if ($result['success']) {
        echo "✅ Weather download successful: {$result['message']}\n";

        // Check if data was saved
        $station->refresh();
        $weatherData = json_decode($station->accuweather, true);

        if ($weatherData) {
            echo "✅ Weather data saved successfully\n";
            echo "   - Location Key: " . ($weatherData['LocationKey'] ?? 'Not set') . "\n";
            echo "   - 12h Forecast: " . (isset($weatherData['12HoursForecast']) ? count($weatherData['12HoursForecast']) . ' entries' : 'Not available') . "\n";
            echo "   - 5d Forecast: " . (isset($weatherData['5DaysForecast']) ? count($weatherData['5DaysForecast']) . ' entries' : 'Not available') . "\n";
            echo "   - Last Updated: " . ($weatherData['last_updated'] ?? 'Not set') . "\n";
        } else {
            echo "⚠️  Weather data could not be decoded\n";
        }
    } else {
        echo "❌ Weather download failed: {$result['message']}\n";
    }
} catch (Exception $e) {
    echo "❌ Exception during weather download: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Test bulk download for all stations (only first 3 to avoid hitting API limits)
echo "📊 Testing bulk weather download (limited to first 3 active stations)...\n";

$testStations = Station::where('active', true)->limit(3)->get();
$successCount = 0;
$failureCount = 0;

foreach ($testStations as $testStation) {
    echo "   Testing station: {$testStation->name}... ";

    try {
        $result = $testStation->downloadAccuweatherData();
        if ($result['success']) {
            echo "✅ Success\n";
            $successCount++;
        } else {
            echo "❌ Failed: {$result['message']}\n";
            $failureCount++;
        }
    } catch (Exception $e) {
        echo "❌ Exception: {$e->getMessage()}\n";
        $failureCount++;
    }
}

echo "\n📈 Bulk test results:\n";
echo "   - Successful: {$successCount}\n";
echo "   - Failed: {$failureCount}\n";
echo "   - Total: " . ($successCount + $failureCount) . "\n\n";

echo "=== Test Complete ===\n";
echo "Check the logs/accuweather.log file for detailed logging information.\n";