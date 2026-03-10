<?php

namespace RayhanBapari\SslcommerzPayment\Exceptions;

use Exception;

class SslcommerzException extends Exception
{
    public function __construct(string $message = 'SSLCommerz payment operation failed.')
    {
        parent::__construct($message);
    }
}
