<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\API\V5\AuthController;
use App\Http\Requests\API\V5\CheckUserV5Request;

echo "🧪 TESTING V5 AUTH DIRECTLY\n";
echo "============================\n\n";

try {
    // Create a mock request
    $data = [
        'email' => 'test.admin@boukii.com',
        'password' => 'password123'
    ];
    
    // Create the request manually
    $request = Request::create('/api/v5/auth/check-user', 'POST', $data);
    $request->headers->set('Accept', 'application/json');
    $request->headers->set('Content-Type', 'application/json');
    
    // Create controller instance
    $controller = app(AuthController::class);
    
    // Create form request instance with proper validation context
    $formRequest = CheckUserV5Request::createFromBase($request);
    $formRequest->setContainer(app());
    $formRequest->setRedirector(app('redirect'));
    $formRequest->merge($data);
    
    // Validate the request
    $formRequest->validateResolved();
    
    echo "📍 Testing checkUser method...\n";
    $response = $controller->checkUser($formRequest);
    
    $responseData = json_decode($response->getContent(), true);
    echo "📊 Response Status: " . $response->getStatusCode() . "\n";
    echo "📋 Response Data: " . json_encode($responseData, JSON_PRETTY_PRINT) . "\n";
    
    if ($response->getStatusCode() === 200 && $responseData['success'] ?? false) {
        echo "✅ AUTH TEST PASSED: User authentication successful\n";
        echo "🏫 Schools returned: " . count($responseData['data']['schools'] ?? []) . "\n";
        echo "🔑 Temp token returned: " . (isset($responseData['data']['temp_token']) ? 'YES' : 'NO') . "\n";
    } else {
        echo "❌ AUTH TEST FAILED\n";
        echo "   Message: " . ($responseData['message'] ?? 'No message') . "\n";
        echo "   Errors: " . json_encode($responseData['errors'] ?? [], JSON_PRETTY_PRINT) . "\n";
    }
    
} catch (\Throwable $e) {
    echo "💥 EXCEPTION OCCURRED:\n";
    echo "   Message: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🎯 TEST COMPLETE\n";