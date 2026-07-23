<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    // Restreint au(x) frontend(s) connu(s) - liste séparée par des virgules
    'allowed_origins' => array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173'))
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // credentials requis pour Sanctum (cookies de session cross-origin)
    'supports_credentials' => true,
];
