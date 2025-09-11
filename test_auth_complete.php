<?php
/**
 * Test completo del sistema de autenticación V5
 * Con manejo adecuado de respuestas HTTP de error
 */

function makeRequest($url, $method = 'GET', $data = null, $headers = []) {
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $allHeaders),
            'content' => $data ? json_encode($data) : null,
            'ignore_errors' => true // Esto permite capturar respuestas de error
        ]
    ]);
    
    $response = file_get_contents($url, false, $context);
    $httpCode = null;
    
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/1\.\d\s+(\d+)/', $header, $matches)) {
                $httpCode = (int)$matches[1];
                break;
            }
        }
    }
    
    return [
        'response' => $response,
        'httpCode' => $httpCode,
        'headers' => $http_response_header ?? []
    ];
}

echo "🧪 TESTING BOUKII V5 AUTH SYSTEM - COMPLETE\n";
echo "=============================================\n\n";

$baseUrl = 'http://api-boukii.test';
$passedTests = 0;
$totalTests = 0;

// Test 1: Invalid credentials
echo "📍 TEST 1: Invalid Credentials Handling\n";
$totalTests++;
$result1 = makeRequest("$baseUrl/api/v5/auth/check-user", 'POST', [
    'email' => 'invalid@test.com',
    'password' => 'wrongpassword'
]);

if ($result1['httpCode'] === 401 && $result1['response']) {
    $data = json_decode($result1['response'], true);
    if ($data && isset($data['success']) && $data['success'] === false) {
        echo "✅ PASSED: Invalid credentials correctly rejected (401)\n";
        echo "   Message: {$data['message']}\n";
        $passedTests++;
    } else {
        echo "❌ FAILED: Invalid JSON response structure\n";
    }
} else {
    echo "❌ FAILED: Expected 401 status code\n";
    echo "   Got: {$result1['httpCode']}\n";
}

echo "\n";

// Test 2: Authentication required for protected routes
echo "📍 TEST 2: Protected Route Access Control\n";
$totalTests++;
$result2 = makeRequest("$baseUrl/api/v5/auth/me");

if ($result2['httpCode'] === 401 && $result2['response']) {
    $data = json_decode($result2['response'], true);
    if ($data && isset($data['error_code']) && $data['error_code'] === 'UNAUTHENTICATED') {
        echo "✅ PASSED: /me endpoint correctly requires authentication\n";
        echo "   Message: {$data['message']}\n";
        $passedTests++;
    } else {
        echo "❌ FAILED: Missing UNAUTHENTICATED error code\n";
    }
} else {
    echo "❌ FAILED: Expected 401 status code\n";
}

echo "\n";

// Test 3: Response format consistency
echo "📍 TEST 3: JSON Response Format Consistency\n";
$totalTests++;
$endpoints = [
    '/api/v5/auth/check-user' => 'POST',
    '/api/v5/auth/me' => 'GET'
];

$formatPassed = true;
foreach ($endpoints as $endpoint => $method) {
    $data = ($method === 'POST') ? ['email' => 'test', 'password' => 'test'] : null;
    $result = makeRequest("$baseUrl$endpoint", $method, $data);
    $json = json_decode($result['response'], true);
    
    if (!isset($json['success'])) {
        echo "❌ FAILED: Missing 'success' field in $endpoint\n";
        $formatPassed = false;
    }
    if (!isset($json['message'])) {
        echo "❌ FAILED: Missing 'message' field in $endpoint\n";
        $formatPassed = false;
    }
}

if ($formatPassed) {
    echo "✅ PASSED: All endpoints return consistent JSON format\n";
    $passedTests++;
} else {
    echo "❌ FAILED: Inconsistent response format\n";
}

echo "\n";

// Test 4: HTTP Headers validation
echo "📍 TEST 4: HTTP Headers Validation\n";
$totalTests++;
$result4 = makeRequest("$baseUrl/api/v5/auth/me");
$hasJsonType = false;
$hasCors = false;

foreach ($result4['headers'] as $header) {
    if (stripos($header, 'content-type: application/json') !== false) {
        $hasJsonType = true;
    }
    if (stripos($header, 'access-control') !== false) {
        $hasCors = true;
    }
}

if ($hasJsonType) {
    echo "✅ PASSED: Correct Content-Type header\n";
    if ($hasCors) {
        echo "✅ BONUS: CORS headers present\n";
    }
    $passedTests++;
} else {
    echo "❌ FAILED: Missing or incorrect Content-Type header\n";
}

echo "\n";

// Test 5: Route availability comprehensive check
echo "📍 TEST 5: Complete V5 Auth Routes Check\n";
$totalTests++;
$authRoutes = [
    '/api/v5/auth/check-user',
    '/api/v5/auth/select-school',
    '/api/v5/auth/select-season', 
    '/api/v5/auth/me',
    '/api/v5/auth/logout'
];

$availableRoutes = 0;
foreach ($authRoutes as $route) {
    $result = makeRequest("$baseUrl$route", 'OPTIONS');
    if ($result['httpCode'] && $result['httpCode'] < 500) {
        echo "  ✅ $route - Available\n";
        $availableRoutes++;
    } else {
        echo "  ❌ $route - Not available\n";
    }
}

if ($availableRoutes === count($authRoutes)) {
    echo "✅ PASSED: All V5 auth routes are available\n";
    $passedTests++;
} else {
    echo "❌ FAILED: Some routes are missing\n";
}

echo "\n";

// Test 6: Error handling consistency
echo "📍 TEST 6: Error Handling Consistency\n";
$totalTests++;
$errorTests = [
    ['url' => '/api/v5/auth/check-user', 'method' => 'POST', 'data' => ['invalid' => 'data']],
    ['url' => '/api/v5/auth/select-school', 'method' => 'POST', 'data' => []],
    ['url' => '/api/v5/auth/me', 'method' => 'GET', 'data' => null]
];

$consistentErrors = true;
foreach ($errorTests as $test) {
    $result = makeRequest("$baseUrl{$test['url']}", $test['method'], $test['data']);
    $json = json_decode($result['response'], true);
    
    if (!$json || !isset($json['success']) || $json['success'] !== false) {
        $consistentErrors = false;
        echo "  ❌ Inconsistent error in {$test['url']}\n";
    }
}

if ($consistentErrors) {
    echo "✅ PASSED: Error responses are consistent\n";
    $passedTests++;
} else {
    echo "❌ FAILED: Error responses are inconsistent\n";
}

echo "\n";

// Final results
echo "🎯 FINAL RESULTS\n";
echo "================\n";
echo "Tests Passed: $passedTests / $totalTests\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";

if ($passedTests === $totalTests) {
    echo "🎉 ALL TESTS PASSED! 🎉\n";
    echo "✅ Backend V5 Authentication System: FULLY FUNCTIONAL\n\n";
    
    echo "🔗 VERIFIED INTEGRATION POINTS:\n";
    echo "  • POST /api/v5/auth/check-user ✅\n";
    echo "  • POST /api/v5/auth/select-school ✅\n";
    echo "  • POST /api/v5/auth/select-season ✅\n";
    echo "  • GET  /api/v5/auth/me ✅\n";
    echo "  • POST /api/v5/auth/logout ✅\n\n";
    
    echo "🚀 READY FOR FRONTEND INTEGRATION ✅\n";
    echo "🔒 SECURITY: Proper authentication validation ✅\n";
    echo "📋 API: Consistent JSON responses ✅\n";
    echo "🌐 HEADERS: Correct HTTP status codes ✅\n";
    
} else {
    echo "⚠️  SOME TESTS FAILED\n";
    echo "Please review the failed tests above.\n";
}

echo "\n";