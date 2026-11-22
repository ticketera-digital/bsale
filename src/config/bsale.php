<?php

return [
    'access_token' => env('BSALE_ACCESS_TOKEN'),
    'base_url' => env('BSALE_BASE_URL'),
    'default_office_id' => env('DEFAULT_OFFICE_ID'),
    'connect_timeout' => env('BSALE_CONNECT_TIMEOUT', 5),
    'timeout' => env('BSALE_TIMEOUT', 20),
    'routeMiddleware' => ['api'],
    'routePrefix' => 'api/bsale',
    'enableWebHooks' => env('BSALE_WEBHOOKS_ENABLED', true),
];
