<?php

namespace RayhanBapari\SslcommerzPayment\Facades;

use Illuminate\Support\Facades\Facade;
use RayhanBapari\SslcommerzPayment\DTOs\PaymentData;
use RayhanBapari\SslcommerzPayment\DTOs\PaymentResponse;

/**
 * @method static PaymentResponse  initiatePayment(PaymentData $paymentData)
 * @method static array            orderValidate(string $valId)
 * @method static bool             verifyIpnHash(array $postData)
 * @method static array            transactionQueryById(string $tranId)
 * @method static array            transactionQueryBySessionId(string $sessionKey)
 * @method static array            initiateRefund(string $bankTranId, float $refundAmount, string $refundRemarks = '', string $refe_id = '')
 * @method static array            refundStatus(string $refundRefId)
 * @method static PaymentData      makePaymentData(string $tranId, float|string $amount, string $productName, string $cusName, string $cusEmail, string $cusPhone)
 *
 * @see \RayhanBapari\SslcommerzPayment\Services\SslcommerzPaymentService
 */
class Sslcommerz extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'sslcommerz-payment';
    }
}
