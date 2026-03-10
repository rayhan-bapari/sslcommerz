<?php

namespace RayhanBapari\SslcommerzPayment\DTOs;

/**
 * Wraps the SSLCommerz payment initiation API response.
 *
 * A successful response contains `status = SUCCESS` and a `GatewayPageURL`.
 */
class PaymentResponse
{
    public function __construct(protected array $data) {}

    /** True if SSLCommerz successfully created the payment session. */
    public function success(): bool
    {
        return isset($this->data['status'])
            && strtoupper($this->data['status']) === 'SUCCESS';
    }

    /** The URL to redirect the customer to for payment. */
    public function gatewayPageURL(): ?string
    {
        return $this->data['GatewayPageURL'] ?? null;
    }

    /** The session key for this transaction (used in transaction queries). */
    public function sessionKey(): ?string
    {
        return $this->data['sessionkey'] ?? null;
    }

    /** Error description returned by SSLCommerz on failure. */
    public function error(): ?string
    {
        return $this->data['failedreason'] ?? $this->data['status'] ?? null;
    }

    /** Get the full raw response array. */
    public function toArray(): array
    {
        return $this->data;
    }

    /** Get a specific field from the response. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
