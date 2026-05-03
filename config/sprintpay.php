<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SprintPay (Nigeria) — hosted card checkout
    |--------------------------------------------------------------------------
    |
    | See sprintpay/sprintpay-api-php-client: POST /payement/card/hosted/url
    | Headers: datetime, authorization (per your SprintPay merchant account).
    |
    */

    'enabled' => (bool) env('SPRINTPAY_ENABLED', false),

    'base_url' => env('SPRINTPAY_BASE_URL', ''),

    'datetime_header' => env('SPRINTPAY_DATETIME', ''),

    'authorization_header' => env('SPRINTPAY_AUTHORIZATION', ''),
];
