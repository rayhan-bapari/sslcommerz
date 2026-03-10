<?php

namespace RayhanBapari\SslcommerzPayment\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RayhanBapari\SslcommerzPayment\Exceptions\SslcommerzIpnException;

/**
 * Handles SSLCommerz IPN (Instant Payment Notification) POST messages.
 *
 * SSLCommerz sends a POST request to your configured IPN URL after every
 * transaction event. This happens server-to-server — even if the customer
 * never returns to your site.
 *
 * IPN POST fields (key ones):
 *   tran_id, val_id, amount, store_amount, bank_tran_id
 *   status        → VALID | VALIDATED | INVALID_TRANSACTION | FAILED | CANCELLED | UNATTEMPTED | EXPIRED
 *   currency, currency_amount, currency_rate
 *   card_type, card_no, card_issuer, card_brand, card_issuer_country
 *   cus_name, cus_email, cus_phone
 *   ship_name, ship_add1 ...
 *   verify_sign, verify_key   ← used for hash verification
 *   value_a, value_b, value_c, value_d  ← your custom pass-through fields
 *
 * ❗ IPN does NOT work on localhost — configure your merchant panel with a
 *    publicly accessible URL (e.g. use ngrok for local testing).
 */
class SslcommerzIpnService
{
    public function __construct(
        protected SslcommerzPaymentService $paymentService
    ) {}

    /**
     * Process an incoming SSLCommerz IPN POST request.
     *
     * Returns a structured result array. Fires a Laravel event on success.
     *
     * @throws SslcommerzIpnException
     */
    public function handle(Request $request): array
    {
        $postData = $request->post();

        if (empty($postData)) {
            throw new SslcommerzIpnException('Empty IPN payload received.');
        }

        // Step 1: Verify the hash signature
        if (!$this->paymentService->verifyIpnHash($postData)) {
            Log::warning('[SSLCommerz IPN] Hash verification failed.', [
                'ip'     => $request->ip(),
                'tran_id'=> $postData['tran_id'] ?? null,
            ]);
            throw new SslcommerzIpnException('IPN hash verification failed — possible tampering.');
        }

        // Step 2: Log raw payload
        if (config('sslcommerz.ipn.log_payloads', true)) {
            $this->logPayload($postData);
        }

        $tranId     = $postData['tran_id']      ?? null;
        $valId      = $postData['val_id']        ?? null;
        $status     = $postData['status']        ?? null;
        $amount     = $postData['amount']        ?? null;
        $storeAmount= $postData['store_amount']  ?? null;
        $bankTranId = $postData['bank_tran_id']  ?? null;
        $currency   = $postData['currency']      ?? null;
        $cardType   = $postData['card_type']     ?? null;

        // Step 3: For VALID transactions, cross-validate with SSLCommerz server
        $validated = false;
        $validationResponse = [];

        if ($valId && in_array($status, ['VALID', 'VALIDATED'])) {
            try {
                $validationResponse = $this->paymentService->orderValidate($valId);
                $validated = isset($validationResponse['status'])
                    && in_array($validationResponse['status'], ['VALID', 'VALIDATED']);
            } catch (\Throwable $e) {
                Log::error('[SSLCommerz IPN] Order validation call failed.', ['error' => $e->getMessage()]);
            }
        }

        $result = [
            'verified'           => $validated,
            'status'             => $status,
            'tran_id'            => $tranId,
            'val_id'             => $valId,
            'bank_tran_id'       => $bankTranId,
            'amount'             => $amount,
            'store_amount'       => $storeAmount,
            'currency'           => $currency,
            'card_type'          => $cardType,
            'card_no'            => $postData['card_no']            ?? null,
            'card_issuer'        => $postData['card_issuer']        ?? null,
            'card_brand'         => $postData['card_brand']         ?? null,
            'card_issuer_country'=> $postData['card_issuer_country']?? null,
            'cus_name'           => $postData['cus_name']           ?? null,
            'cus_email'          => $postData['cus_email']          ?? null,
            'cus_phone'          => $postData['cus_phone']          ?? null,
            'value_a'            => $postData['value_a']            ?? null,
            'value_b'            => $postData['value_b']            ?? null,
            'value_c'            => $postData['value_c']            ?? null,
            'value_d'            => $postData['value_d']            ?? null,
            'validation_response'=> $validationResponse,
            'raw'                => $postData,
        ];

        return $result;
    }

    // -------------------------------------------------------------------------
    // Logging
    // -------------------------------------------------------------------------

    protected function logPayload(array $postData): void
    {
        try {
            $this->ensureIpnLogTableExists();

            DB::table('sslcz_ipn_logs')->insert([
                'tran_id'    => $postData['tran_id']     ?? null,
                'val_id'     => $postData['val_id']      ?? null,
                'status'     => $postData['status']      ?? null,
                'amount'     => $postData['amount']      ?? null,
                'currency'   => $postData['currency']    ?? null,
                'bank_tran_id' => $postData['bank_tran_id'] ?? null,
                'raw_payload'=> json_encode($postData),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[SSLCommerz IPN] Failed to log payload.', ['error' => $e->getMessage()]);
        }
    }

    protected function ensureIpnLogTableExists(): void
    {
        if (Schema::hasTable('sslcz_ipn_logs')) {
            return;
        }

        Schema::create('sslcz_ipn_logs', function ($t) {
            $t->id();
            $t->string('tran_id')->nullable()->index();
            $t->string('val_id')->nullable()->index();
            $t->string('status', 50)->nullable();
            $t->string('amount')->nullable();
            $t->string('currency', 10)->nullable();
            $t->string('bank_tran_id')->nullable()->index();
            $t->longText('raw_payload');
            $t->timestamps();
        });
    }
}
