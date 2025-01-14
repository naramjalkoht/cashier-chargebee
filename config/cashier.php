<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chargebee API Configuration
    |--------------------------------------------------------------------------
    |
    | The site name is used to construct the base URL for all Chargebee API
    | requests, while the API key authenticates your application when making
    | those requests.
    |
    */

    'site' => env('CHARGEBEE_SITE'),

    'api_key' => env('CHARGEBEE_API_KEY'),
];
