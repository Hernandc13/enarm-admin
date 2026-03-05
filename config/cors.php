<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Aquí defines qué orígenes pueden consumir tu API desde el navegador.
    | Para DEV local permitimos localhost y 127.0.0.1 con cualquier puerto.
    |
    */

    'paths' => [
        'api/*',
        // si en algún momento usas cookies de Sanctum (SPA),
        // necesitarías también:
        // 'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    // ✅ DEV: permitir Flutter web en localhost/127.0.0.1 (cualquier puerto)
    'allowed_origins' => [
        'http://localhost',
        'http://127.0.0.1',
    ],

    // ✅ Permite patrones con puerto variable (ej. http://localhost:45411)
    'allowed_origins_patterns' => [
        '#^http://localhost(:\d+)?$#',
        '#^http://127\.0\.0\.1(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    // Si quieres leer headers personalizados desde el navegador, ponlos aquí
    'exposed_headers' => [
        // 'Authorization',
    ],

    'max_age' => 0,

    // ✅ Para tu caso (tokens Bearer) NO se requieren credenciales/cookies
    'supports_credentials' => false,

];
