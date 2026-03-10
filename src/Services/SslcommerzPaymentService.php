<?php

namespace RayhanBapari\SslcommerzPayment\Services;

use Illuminate\Support\Facades\Log;
use RayhanBapari\SslcommerzPayment\Contracts\SslcommerzPaymentInterface;
use RayhanBapari\SslcommerzPayment\DTOs\PaymentData;
use RayhanBapari\SslcommerzPayment\DTOs\PaymentResponse;
use RayhanBapari\SslcommerzPayment\Exceptions\SslcommerzException;

/**
 * Core SSLCommerz payment service.
 *
 * Implements the full SSLCommerz v4 REST API:
 *  - Payment initiation (hosted & checkout modes)
 *  - Order validation (val_id)
 *  - IPN hash verification (MD5)
 *  - Transaction query by tran_id and sessionkey
 *  - Refund initiation and refund status
 *
 * @see https://developer.sslcommerz.com/doc/v4/
 */
class SslcommerzPaymentService implements SslcommerzPaymentInterface
{
    protected string $baseUrl;
    protected string $storeId;
    protected string $storePassword;
    protected bool   $sandbox;

    // Endpoint paths (relative)
    protected const ENDPOINT_INITIATE         = '/gwprocess/v4/api.php';
    protected const ENDPOINT_VALIDATE         = '/validator/api/validationserverAPI.php';
    protected const ENDPOINT_TXN_BY_ID        = '/validator/api/merchantTransIDvalidationAPI.php';
    protected const ENDPOINT_TXN_BY_SESSION   = '/validator/api/sessionvalidationAPI.php';
    protected const ENDPOINT_REFUND_INITIATE  = '/validator/api/merchantTransIDvalidationAPI.php';
    protected const ENDPOINT_REFUND_STATUS    = '/validator/api/validationserverAPI.php';

    public function __construct()
    {
        $this->sandbox       = (bool) config('sslcommerz.sandbox', true);
        $this->storeId       = config('sslcommerz.store_id', '');
        $this->storePassword = config('sslcommerz.store_password', '');
        $this->baseUrl       = $this->sandbox
            ? rtrim(config('sslcommerz.sandbox_base_url', 'https://sandbox.sslcommerz.com'), '/')
            : rtrim(config('sslcommerz.production_base_url', 'https://securepay.sslcommerz.com'), '/');
    }

    // -------------------------------------------------------------------------
    // Payment Initiation
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * @throws SslcommerzException
     */
    public function initiatePayment(PaymentData $paymentData): PaymentResponse
    {
        $this->validateCredentials();

        // Build the POST body
        $postData = $paymentData->toArray();

        // Ensure required callback URLs are present
        foreach (['success_url', 'fail_url', 'cancel_url'] as $required) {
            if (empty($postData[$required])) {
                throw new SslcommerzException("Required parameter [{$required}] is missing for initiatePayment.");
            }
        }

        $raw      = $this->post(self::ENDPOINT_INITIATE, $postData);
        $decoded  = $this->decodeJson($raw, self::ENDPOINT_INITIATE);

        return new PaymentResponse($decoded);
    }

    // -------------------------------------------------------------------------
    // Order Validation
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * @throws SslcommerzException
     */
    public function orderValidate(string $valId): array
    {
        $this->validateCredentials();

        $params = http_build_query([
            'val_id'         => $valId,
            'store_id'       => $this->storeId,
            'store_passwd'   => $this->storePassword,
            'v'              => '1',
            'format'         => 'json',
        ]);

        $url = $this->baseUrl . self::ENDPOINT_VALIDATE . '?' . $params;
        $raw = $this->get($url);

        return $this->decodeJson($raw, self::ENDPOINT_VALIDATE);
    }

    // -------------------------------------------------------------------------
    // IPN Hash Verification
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * SSLCommerz IPN hash algorithm:
     * 1. Grab `verify_sign` and `verify_key` from POST data.
     * 2. Explode `verify_key` by comma → list of POST field names.
     * 3. Build key=value pairs from those fields, append store_passwd=MD5(password).
     * 4. MD5-hash the combined string → must equal `verify_sign`.
     */
    public function verifyIpnHash(array $postData): bool
    {
        if (empty($postData['verify_sign']) || empty($postData['verify_key'])) {
            return false;
        }

        $verifySign = $postData['verify_sign'];
        $keys       = explode(',', $postData['verify_key']);

        $parts = [];
        foreach ($keys as $key) {
            $key = trim($key);
            if (isset($postData[$key])) {
                $parts[] = $key . '=' . $postData[$key];
            }
        }

        // Append store password as MD5
        $parts[] = 'store_passwd=' . md5($this->storePassword);

        $hashString = implode('&', $parts);
        $computed   = md5($hashString);

        return hash_equals($computed, $verifySign);
    }

    // -------------------------------------------------------------------------
    // Transaction Queries
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     */
    public function transactionQueryById(string $tranId): array
    {
        $this->validateCredentials();

        $params = http_build_query([
            'tran_id'      => $tranId,
            'store_id'     => $this->storeId,
            'store_passwd' => $this->storePassword,
            'v'            => '1',
            'format'       => 'json',
        ]);

        $url = $this->baseUrl . self::ENDPOINT_TXN_BY_ID . '?' . $params;
        $raw = $this->get($url);

        return $this->decodeJson($raw, self::ENDPOINT_TXN_BY_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function transactionQueryBySessionId(string $sessionKey): array
    {
        $this->validateCredentials();

        $params = http_build_query([
            'sessionkey'   => $sessionKey,
            'store_id'     => $this->storeId,
            'store_passwd' => $this->storePassword,
            'v'            => '1',
            'format'       => 'json',
        ]);

        $url = $this->baseUrl . self::ENDPOINT_TXN_BY_SESSION . '?' . $params;
        $raw = $this->get($url);

        return $this->decodeJson($raw, self::ENDPOINT_TXN_BY_SESSION);
    }

    // -------------------------------------------------------------------------
    // Refunds
    // -------------------------------------------------------------------------

    /**
     * {@inheritdoc}
     *
     * ❗ Your public IP must be registered with SSLCommerz for live refunds.
     */
    public function initiateRefund(
        string $bankTranId,
        float  $refundAmount,
        string $refundRemarks = '',
        string $refe_id       = ''
    ): array {
        $this->validateCredentials();

        $params = http_build_query(array_filter([
            'bank_tran_id'    => $bankTranId,
            'store_id'        => $this->storeId,
            'store_passwd'    => $this->storePassword,
            'refund_amount'   => number_format($refundAmount, 2, '.', ''),
            'refund_remarks'  => $refundRemarks,
            'refe_id'         => $refe_id,
            'v'               => '1',
            'format'          => 'json',
        ]));

        $url = $this->baseUrl . self::ENDPOINT_REFUND_INITIATE . '?' . $params;
        $raw = $this->get($url);

        return $this->decodeJson($raw, self::ENDPOINT_REFUND_INITIATE);
    }

    /**
     * {@inheritdoc}
     */
    public function refundStatus(string $refundRefId): array
    {
        $this->validateCredentials();

        $params = http_build_query([
            'refund_ref_id' => $refundRefId,
            'store_id'      => $this->storeId,
            'store_passwd'  => $this->storePassword,
            'format'        => 'json',
        ]);

        $url = $this->baseUrl . self::ENDPOINT_REFUND_STATUS . '?' . $params;
        $raw = $this->get($url);

        return $this->decodeJson($raw, self::ENDPOINT_REFUND_STATUS);
    }

    // -------------------------------------------------------------------------
    // HTTP Helpers
    // -------------------------------------------------------------------------

    /**
     * HTTP POST via cURL (used for payment initiation).
     *
     * @throws SslcommerzException
     */
    protected function post(string $path, array $data): string
    {
        $url  = $this->baseUrl . $path;
        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => $this->sandbox ? false : 2,
            CURLOPT_SSL_VERIFYPEER => !$this->sandbox,
        ]);

        $response = curl_exec($curl);
        $errno    = curl_errno($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($errno || $response === false) {
            Log::error('[SSLCommerz] POST cURL error', ['path' => $path, 'error' => $error]);
            throw new SslcommerzException("SSLCommerz connection error: {$error}");
        }

        return $response;
    }

    /**
     * HTTP GET via cURL (used for validation and queries).
     *
     * @throws SslcommerzException
     */
    protected function get(string $fullUrl): string
    {
        $curl = curl_init($fullUrl);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYHOST => $this->sandbox ? false : 2,
            CURLOPT_SSL_VERIFYPEER => !$this->sandbox,
        ]);

        $response = curl_exec($curl);
        $errno    = curl_errno($curl);
        $error    = curl_error($curl);
        curl_close($curl);

        if ($errno || $response === false) {
            Log::error('[SSLCommerz] GET cURL error', ['url' => $fullUrl, 'error' => $error]);
            throw new SslcommerzException("SSLCommerz connection error: {$error}");
        }

        return $response;
    }

    /**
     * Decode JSON response and throw on failure.
     *
     * @throws SslcommerzException
     */
    protected function decodeJson(string $raw, string $context = ''): array
    {
        if (empty($raw)) {
            throw new SslcommerzException("Empty response from SSLCommerz ({$context}).");
        }

        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SslcommerzException("Invalid JSON from SSLCommerz ({$context}): {$raw}");
        }

        return $decoded;
    }

    /**
     * Sanity-check that credentials are configured.
     *
     * @throws SslcommerzException
     */
    protected function validateCredentials(): void
    {
        if (empty($this->storeId) || empty($this->storePassword)) {
            throw new SslcommerzException(
                'SSLCommerz store_id or store_password is not configured. ' .
                'Please set SSLCZ_STORE_ID and SSLCZ_STORE_PASSWORD in your .env file.'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Convenience helpers
    // -------------------------------------------------------------------------

    /**
     * Build a PaymentData DTO fluently. Returns it for further customization.
     */
    public function makePaymentData(
        string $tranId,
        float|string $amount,
        string $productName,
        string $cusName,
        string $cusEmail,
        string $cusPhone
    ): PaymentData {
        $dto               = new PaymentData();
        $dto->tran_id      = $tranId;
        $dto->total_amount = $amount;
        $dto->product_name = $productName;
        $dto->cus_name     = $cusName;
        $dto->cus_email    = $cusEmail;
        $dto->cus_phone    = $cusPhone;
        $dto->currency     = config('sslcommerz.currency', 'BDT');

        return $dto;
    }
}
