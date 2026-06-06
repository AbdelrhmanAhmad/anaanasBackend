<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact form rate limits (Laravel RateLimiter name: contact)
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'per_minute' => (int) env('CONTACT_RATE_LIMIT_PER_MINUTE', 2),
        'per_hour' => (int) env('CONTACT_RATE_LIMIT_PER_HOUR', 8),
        'auth_per_hour' => (int) env('CONTACT_RATE_LIMIT_AUTH_PER_HOUR', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Honeypot & timing (silent reject — bots get fake success)
    |--------------------------------------------------------------------------
    */
    'honeypot' => [
        'field' => 'company_website',
        'timestamp_field' => '_form_ts',
        'min_seconds' => (int) env('CONTACT_HONEYPOT_MIN_SECONDS', 3),
    ],

];
