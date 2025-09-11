<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\V5\DashboardController;

echo "🧪 TESTING V5 DASHBOARD DIRECTLY\n";
echo "==================================\n\n";

try {
    // Create a mock request with auth context
    $request = Request::create('/api/v5/dashboard/stats', 'GET');
    $request->headers->set('Accept', 'application/json');
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('X-School-ID', '1');
    $request->headers->set('X-Season-ID', '1');
    
    // Simulate authenticated user
    $user = \App\Models\User::find(20220); // our test user
    $request->setUserResolver(function () use ($user) {
        return $user;
    });
    
    // Add school and season context (similar to what middleware would do)
    $request->merge([
        '_school_id' => 1,
        '_season_id' => 1,
    ]);
    
    echo "📍 Testing dashboard stats...\n";
    echo "👤 User: {$user->email} (Type: {$user->type})\n";
    echo "🏫 School ID: 1\n";
    
    // Check if DashboardController exists
    if (!class_exists('App\\Http\\Controllers\\V5\\DashboardController')) {
        echo "⚠️ DashboardController not found, trying API version...\n";
        
        // Try API version
        if (class_exists('App\\Http\\Controllers\\API\\V5\\Dashboard\\DashboardController')) {
            $controller = app('App\\Http\\Controllers\\API\\V5\\Dashboard\\DashboardController');
            $response = $controller->getStats($request);
        } else {
            echo "❌ No dashboard controller found\n";
            echo "Available controllers:\n";
            // List V5 controllers
            $controllers = glob(__DIR__ . '/app/Http/Controllers/**/V5/*Controller.php');
            foreach ($controllers as $controllerFile) {
                echo "  - " . basename($controllerFile) . "\n";
            }
            exit;
        }
    } else {
        $controller = app(DashboardController::class);
        $response = $controller->stats($request);
    }
    
    $responseData = json_decode($response->getContent(), true);
    echo "📊 Response Status: " . $response->getStatusCode() . "\n";
    echo "📋 Response Data: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
    
    if ($response->getStatusCode() === 200 && $responseData['success'] ?? false) {
        echo "✅ DASHBOARD TEST PASSED\n";
    } else {
        echo "❌ DASHBOARD TEST FAILED\n";
        echo "   Message: " . ($responseData['message'] ?? 'No message') . "\n";
    }
    
} catch (\Throwable $e) {
    echo "💥 EXCEPTION OCCURRED:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🎯 TEST COMPLETE\n";