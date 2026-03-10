<?php

namespace RayhanBapari\SslcommerzPayment\Contracts;

use RayhanBapari\SslcommerzPayment\DTOs\PaymentData;
use RayhanBapari\SslcommerzPayment\DTOs\PaymentResponse;

interface SslcommerzPaymentInterface
{
    /**
     * Initiate a payment session with SSLCommerz.
     * Returns a PaymentResponse — check ->success() and redirect to ->gatewayPageURL().
     */
    public function initiatePayment(PaymentData $paymentData): PaymentResponse;

    /**
     * Validate a completed transaction by its val_id.
     * Always call this server-side before fulfilling an order.
     *
     * @return array Raw validation response from SSLCommerz
     */
    public function orderValidate(string $valId): array;

    /**
     * Verify the IPN/callback hash signature to confirm the POST came from SSLCommerz.
     * Uses MD5(store_password) in the signing string.
     *
     * @param  array $postData   The full $_POST / request data
     * @return bool
     */
    public function verifyIpnHash(array $postData): bool;

    /**
     * Query a transaction by its merchant tran_id.
     */
    public function transactionQueryById(string $tranId): array;

    /**
     * Query a transaction by its SSLCommerz sessionkey.
     */
    public function transactionQueryBySessionId(string $sessionKey): array;

    /**
     * Initiate a refund for a completed transaction.
     *
     * @param  string $bankTranId      The bank_tran_id from the original transaction
     * @param  float  $refundAmount
     * @param  string $refundRemarks
     * @param  string $refe_id         Optional reference ID for tracking
     */
    public function initiateRefund(
        string $bankTranId,
        float  $refundAmount,
        string $refundRemarks = '',
        string $refe_id       = ''
    ): array;

    /**
     * Check the status of a previously initiated refund.
     *
     * @param  string $refundRefId   The refund_ref_id returned from initiateRefund()
     */
    public function refundStatus(string $refundRefId): array;
}
