<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        // Angular Admin Panel - PRIORITARIO
        env('ADMIN_FRONTEND_URL', 'http://localhost:4200'),
        'https://dev.api.boukii.com',           // Dominio dev del admin Angular
        'https://admin.boukii.com',             // Dominio producción admin
        
        // Otros frontends
        env('FRONTEND_URL', 'http://localhost:4201'),
        env('TEACHER_APP_URL', 'http://localhost:4202'),
        env('BOOKING_PAGE_URL', 'http://localhost:4203'),
    ],

    'allowed_origins_patterns' => [
        // Permitir subdominios de desarrollo
        '#^https?://.*\.localhost(:\d+)?$#',
        // Permitir dominios de producción específicos
        '#^https://.*\.boukii\.com$#',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization', 
        'Content-Type',
        'X-Requested-With',
        'X-School-Slug',
        'X-CSRF-TOKEN',
        'X-Socket-ID',
        'X-Season-Id',
        'X-School-Id',
        'X-Client-Version',
        'X-Client-Type',
    ],

    'exposed_headers' => [
        'X-Pagination-Current-Page',
        'X-Pagination-Per-Page', 
        'X-Pagination-Total',
    ],

    'max_age' => 86400, // 24 hours

    'supports_credentials' => true,

];
