# SSLCommerz Payment for Laravel

[![Packagist Version](https://img.shields.io/packagist/v/rayhan-bapari/sslcommerz-payment.svg)](https://packagist.org/packages/rayhan-bapari/sslcommerz-payment)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%20–%208.5-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-9%20–%2012-red.svg)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A production-ready Laravel package for **SSLCommerz** payment gateway — the most popular payment aggregator in Bangladesh.

**Features:**
- ✅ Hosted & Checkout (popup/embed) payment modes
- ✅ IPN (Instant Payment Notification) handler with MD5 hash verification
- ✅ Order validation via SSLCommerz server-to-server API
- ✅ Transaction query by `tran_id` and `sessionkey`
- ✅ Refund initiation & refund status
- ✅ Strongly typed `PaymentData` DTO covering all API fields
- ✅ `PaymentResponse` object with clean API
- ✅ Full IPN payload logging to database
- ✅ Laravel Facade & dependency injection
- ✅ Laravel 9 / 10 / 11 / 12 · PHP 8.1 / 8.2 / 8.3 / 8.4 / 8.5

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.1 |
| Laravel | ^9.0 \| ^10.0 \| ^11.0 \| ^12.0 |
| ext-curl | * |
| ext-json | * |

---

## Installation

```bash
composer require rayhan-bapari/sslcommerz-payment
```

### Publish config & migrations

```bash
php artisan vendor:publish --tag=sslcommerz-config
php artisan vendor:publish --tag=sslcommerz-migrations
php artisan migrate
```

---

## Configuration

Add the following to your `.env`:

```env
# --- Environment ---
SSLCZ_SANDBOX=true              # true = sandbox, false = live

# --- Store Credentials ---
# Sandbox registration: https://developer.sslcommerz.com/registration/
SSLCZ_STORE_ID=your_store_id
SSLCZ_STORE_PASSWORD=your_store_password

# --- Default Currency ---
SSLCZ_CURRENCY=BDT

# --- Callback URLs (can be overridden per-payment) ---
SSLCZ_SUCCESS_URL=https://yourdomain.com/sslcommerz/success
SSLCZ_FAIL_URL=https://yourdomain.com/sslcommerz/fail
SSLCZ_CANCEL_URL=https://yourdomain.com/sslcommerz/cancel
SSLCZ_IPN_URL=https://yourdomain.com/sslcommerz/ipn

# --- Payment display mode ---
SSLCZ_DISPLAY_TYPE=hosted       # 'hosted' (redirect) or 'checkout' (popup)

# --- IPN ---
SSLCZ_IPN_ENABLED=true
SSLCZ_IPN_PATH=sslcommerz/ipn
SSLCZ_IPN_LOG=true
```

### CSRF Exclusion

SSLCommerz POSTs to your callback and IPN URLs. Exclude them from CSRF verification:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'sslcommerz/success',
    'sslcommerz/fail',
    'sslcommerz/cancel',
    'sslcommerz/ipn',
];
```

> **Session issue?** If your session is destroyed after SSLCommerz redirects back, add `'same_site' => 'none'` in `config/session.php`.

---

## Usage

### Via Facade

```php
use RayhanBapari\SslcommerzPayment\Facades\Sslcommerz;
use RayhanBapari\SslcommerzPayment\DTOs\PaymentData;
```

### Via Dependency Injection

```php
use RayhanBapari\SslcommerzPayment\Contracts\SslcommerzPaymentInterface;

public function __construct(protected SslcommerzPaymentInterface $sslcz) {}
```

---

### 1. Initiate Payment

```php
use RayhanBapari\SslcommerzPayment\DTOs\PaymentData;
use RayhanBapari\SslcommerzPayment\Facades\Sslcommerz;

public function initiatePayment(Request $request)
{
    $dto               = new PaymentData();
    $dto->tran_id      = 'INV-' . uniqid();
    $dto->total_amount = 500.00;
    $dto->currency     = 'BDT';

    // Customer
    $dto->cus_name     = $request->user()->name;
    $dto->cus_email    = $request->user()->email;
    $dto->cus_phone    = $request->user()->phone;
    $dto->cus_add1     = '123 Dhanmondi';
    $dto->cus_city     = 'Dhaka';
    $dto->cus_country  = 'Bangladesh';
    $dto->cus_postcode = '1209';

    // Product
    $dto->product_name     = 'Premium Subscription';
    $dto->product_category = 'subscription';
    $dto->product_profile  = 'non-physical-goods';

    // Shipping (set to NO if digital)
    $dto->shipping_method = 'NO';

    // Pass-through — get them back in success/fail/cancel/ipn callbacks
    $dto->value_a = (string) $request->user()->id;
    $dto->value_b = 'order-reference';

    $response = Sslcommerz::initiatePayment($dto);

    if ($response->success()) {
        return redirect($response->gatewayPageURL());
    }

    return back()->with('error', 'Could not initiate payment: ' . $response->error());
}
```

---

### 2. Handle Callbacks

Define routes for SSLCommerz to POST back to:

```php
// routes/web.php
Route::post('sslcommerz/success', [PaymentController::class, 'success'])->name('sslcommerz.success');
Route::post('sslcommerz/fail',    [PaymentController::class, 'fail'])->name('sslcommerz.fail');
Route::post('sslcommerz/cancel',  [PaymentController::class, 'cancel'])->name('sslcommerz.cancel');
```

```php
// PaymentController.php

public function success(Request $request)
{
    // Step 1: Verify hash
    if (!Sslcommerz::verifyIpnHash($request->post())) {
        abort(403, 'Invalid signature.');
    }

    // Step 2: Server-side validation
    $validation = Sslcommerz::orderValidate($request->val_id);

    if (!in_array($validation['status'] ?? '', ['VALID', 'VALIDATED'])) {
        return redirect()->route('checkout')->with('error', 'Payment validation failed.');
    }

    // Step 3: Confirm the amount matches your order
    $paidAmount = (float) $validation['amount'];
    // ... compare with your stored order amount

    // Step 4: Fulfill order using $request->value_a (your user ID), tran_id, bank_tran_id
    $bankTranId = $request->bank_tran_id; // Save this — needed for refunds

    return redirect()->route('dashboard')->with('success', 'Payment successful!');
}

public function fail(Request $request)
{
    return redirect()->route('checkout')->with('error', 'Payment failed. Please try again.');
}

public function cancel(Request $request)
{
    return redirect()->route('checkout')->with('info', 'Payment cancelled.');
}
```

---

### 3. IPN Handler

The package auto-registers `POST /sslcommerz/ipn`. Subscribe to the event:

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    'sslcommerz.ipn' => [
        App\Listeners\HandleSslcommerzIpn::class,
    ],
];
```

```php
// app/Listeners/HandleSslcommerzIpn.php
namespace App\Listeners;

class HandleSslcommerzIpn
{
    public function handle(array $payload): void
    {
        if (!$payload['verified']) {
            return; // Hash check passed but server validation failed
        }

        $tranId     = $payload['tran_id'];
        $status     = $payload['status'];      // VALID | VALIDATED
        $bankTranId = $payload['bank_tran_id']; // Save for refunds
        $amount     = $payload['amount'];
        $userId     = $payload['value_a'];      // Your pass-through field

        // Update your order in the database here
    }
}
```

> **Note:** IPN does not work on localhost. Use a public URL (e.g. ngrok for local dev). Also configure the IPN URL in your SSLCommerz merchant panel.

---

### 4. Transaction Query

```php
// By your tran_id
$result = Sslcommerz::transactionQueryById('INV-12345');

// By SSLCommerz sessionkey
$result = Sslcommerz::transactionQueryBySessionId('session_abc123');
```

---

### 5. Refund

```php
// You need the bank_tran_id from the original transaction
$result = Sslcommerz::initiateRefund(
    bankTranId:    '1709162345070ANJdZV8LyI4cMw',
    refundAmount:  50.00,
    refundRemarks: 'Customer request',
    refe_id:       'REF-TRACK-001'   // optional tracking reference
);

// $result['status'] → 'success' | 'failed' | 'processing'
// $result['refund_ref_id'] → save this for status checking

// Check refund status
$status = Sslcommerz::refundStatus($result['refund_ref_id']);
// $status['status'] → 'refunded' | 'processing' | 'cancelled'
```

> **Note:** Your public server IP must be registered with SSLCommerz for live refunds.

---

### 6. Popup / Checkout Mode

For embedded checkout, set `SSLCZ_DISPLAY_TYPE=checkout` and add the SSLCommerz embed script to your blade template:

```html
<button id="sslczPayBtn"
    postdata=""
    endpoint="/sslcommerz/pay-via-ajax">
    Pay Now
</button>

<script>
    var obj = {};
    obj.cus_name  = '{{ auth()->user()->name }}';
    obj.cus_email = '{{ auth()->user()->email }}';
    obj.amount    = '500';
    $('#sslczPayBtn').prop('postdata', obj);
</script>

{{-- Sandbox --}}
<script>
    (function(w, d) {
        var s = d.createElement('script');
        s.src = 'https://sandbox.sslcommerz.com/embed.min.js?' + Math.random().toString(36).substring(7);
        d.getElementsByTagName('script')[0].parentNode.insertBefore(s, d.getElementsByTagName('script')[0]);
    })(window, document);
</script>
```

For live, replace the sandbox embed URL with:
```
https://seamless-epay.sslcommerz.com/embed.min.js
```

---

## Database Tables

| Table | Purpose |
|---|---|
| `sslcz_ipn_logs` | Raw + parsed IPN POST payloads |

---

## Error Handling

```php
use RayhanBapari\SslcommerzPayment\Exceptions\SslcommerzException;
use RayhanBapari\SslcommerzPayment\Exceptions\SslcommerzIpnException;

try {
    $response = Sslcommerz::initiatePayment($dto);
} catch (SslcommerzException $e) {
    // Configuration or API communication error
    Log::error('SSLCommerz error: ' . $e->getMessage());
}
```

---

## Sandbox Testing

Get free sandbox credentials: [https://developer.sslcommerz.com/registration/](https://developer.sslcommerz.com/registration/)

Set `SSLCZ_SANDBOX=true` and use your sandbox `store_id` and `store_password`.

---

## Transaction Status Values

| Status | Meaning |
|---|---|
| `VALID` | Transaction is valid — fulfill immediately |
| `VALIDATED` | Already validated once — still safe |
| `INVALID_TRANSACTION` | Something is wrong |
| `FAILED` | Payment failed at gateway |
| `CANCELLED` | Customer cancelled |
| `UNATTEMPTED` | Customer did not attempt |
| `EXPIRED` | Session expired |

---

## License

MIT © [Rayhan Bapari](https://github.com/rayhan-bapari)
# sslcommerz
