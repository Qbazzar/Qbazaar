<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
| Only the web app's origins may call the API from a browser. Native apps
| are not subject to CORS. CORS_ALLOWED_ORIGINS takes a comma-separated
| list and defaults to WEB_URL.
*/

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) (env('CORS_ALLOWED_ORIGINS') ?: env('WEB_URL', 'http://localhost:3000'))),
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
