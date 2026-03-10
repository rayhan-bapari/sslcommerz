<?php

use Illuminate\Support\Facades\Route;
use RayhanBapari\SslcommerzPayment\Http\Controllers\SslcommerzIpnController;

/*
|--------------------------------------------------------------------------
| SSLCommerz IPN Route
|--------------------------------------------------------------------------
|
| SSLCommerz sends a POST to this URL after every payment event.
| Configure this URL in your SSLCommerz merchant panel:
|   https://sandbox.sslcommerz.com (sandbox)
|   https://securepay.sslcommerz.com (live)
|
| Default path : POST /sslcommerz/ipn
| Override via : SSLCZ_IPN_PATH in .env
|
| ❗ This endpoint must be excluded from CSRF verification.
|    Add the path to $except in App\Http\Middleware\VerifyCsrfToken.
|
*/

Route::post(
    config('sslcommerz.ipn.path', 'sslcommerz/ipn'),
    SslcommerzIpnController::class
)
->middleware(config('sslcommerz.ipn.middleware', ['web']))
->name('sslcommerz.ipn');
