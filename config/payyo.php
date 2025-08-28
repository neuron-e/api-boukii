<?php

return [
    'instance' => env('PAYYO_INSTANCE'),
    'key' => env('PAYYO_KEY'),
    'api_base_domain' => env('PAYYO_API_BASE_DOMAIN', 'api.payyo.com'),
    'gateway_endpoint' => env('PAYYO_GATEWAY_ENDPOINT', '/v1/gateway'),
    'transaction_endpoint' => env('PAYYO_TRANSACTION_ENDPOINT', '/v1/transaction'),
];

