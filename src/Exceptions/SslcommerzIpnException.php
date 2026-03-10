<?php

namespace RayhanBapari\SslcommerzPayment\Exceptions;

use Exception;

class SslcommerzIpnException extends Exception
{
    public function __construct(string $message = 'SSLCommerz IPN processing failed.')
    {
        parent::__construct($message);
    }
}
