<?php

return [
    // URL du serveur central (vide = sync désactivé, le POS fonctionne en standalone)
    'central_url'   => env('CENTRAL_SERVER_URL', ''),

    // Identifiants du terminal — à configurer dans .env de chaque caisse
    'terminal_id'   => env('TERMINAL_ID', 'pos-' . gethostname()),
    'restaurant_id' => env('RESTAURANT_ID', 'restaurant-1'),

    // Clé API partagée entre ce terminal et le serveur central
    'api_key'       => env('CENTRAL_API_KEY', ''),
];
