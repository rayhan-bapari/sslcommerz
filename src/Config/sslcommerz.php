<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    | Set to true to use the SSLCommerz sandbox (testing) environment.
    | Set to false to use the live/production environment.
    |
    | Sandbox base URL : https://sandbox.sslcommerz.com
    | Live base URL    : https://securepay.sslcommerz.com
    |
    */
    'sandbox' => env('SSLCZ_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | Store Credentials
    |--------------------------------------------------------------------------
    | Provided by SSLCommerz during merchant on-boarding.
    | Sandbox credentials: https://developer.sslcommerz.com/registration/
    |
    */
    'store_id'       => env('SSLCZ_STORE_ID', ''),
    'store_password' => env('SSLCZ_STORE_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | API Base URLs
    |--------------------------------------------------------------------------
    */
    'sandbox_base_url'    => 'https://sandbox.sslcommerz.com',
    'production_base_url' => 'https://securepay.sslcommerz.com',

    /*
    |--------------------------------------------------------------------------
    | API Endpoints (relative to base URL)
    |--------------------------------------------------------------------------
    */
    'endpoints' => [
        'initiate'              => '/gwprocess/v4/api.php',
        'validate'              => '/validator/api/validationserverAPI.php',
        'transaction_by_id'     => '/validator/api/merchantTransIDvalidationAPI.php',
        'transaction_by_session'=> '/validator/api/sessionvalidationAPI.php',
        'refund_initiate'       => '/validator/api/merchantTransIDvalidationAPI.php',
        'refund_status'         => '/validator/api/validationserverAPI.php',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('SSLCZ_CURRENCY', 'BDT'),

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    | These are the routes SSLCommerz will POST back to after a transaction.
    | Set via route name or full URL. Can also be overridden per-payment.
    |
    */
    'success_url' => env('SSLCZ_SUCCESS_URL', null),
    'fail_url'    => env('SSLCZ_FAIL_URL', null),
    'cancel_url'  => env('SSLCZ_CANCEL_URL', null),
    'ipn_url'     => env('SSLCZ_IPN_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Payment Display Mode
    |--------------------------------------------------------------------------
    | 'hosted'   → Redirect customer to SSLCommerz hosted payment page.
    | 'checkout' → Embed payment gateway directly in your site (popup/iframe).
    |
    */
    'payment_display_type' => env('SSLCZ_DISPLAY_TYPE', 'hosted'),

    /*
    |--------------------------------------------------------------------------
    | IPN / Webhook
    |--------------------------------------------------------------------------
    | The package auto-registers a POST route for the IPN listener.
    | SSLCommerz will POST transaction notifications to this URL.
    |
    | ❗ IPN does NOT work on localhost — requires a public server.
    | Configure the IPN URL in your SSLCommerz merchant panel.
    |
    | log_payloads → Store every raw IPN POST in bkash_sslcz_ipn_logs table.
    |
    */
    'ipn' => [
        'enabled'      => env('SSLCZ_IPN_ENABLED', true),
        'path'         => env('SSLCZ_IPN_PATH', 'sslcommerz/ipn'),
        'middleware'   => ['web'],
        'log_payloads' => env('SSLCZ_IPN_LOG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session / Cookie Fix
    |--------------------------------------------------------------------------
    | SSLCommerz redirects may destroy the Laravel session on some setups
    | due to SameSite cookie policy. If you experience session loss after
    | redirect, set 'same_site' => 'none' in config/session.php.
    |
    */

];
