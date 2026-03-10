<?php

namespace RayhanBapari\SslcommerzPayment\DTOs;

/**
 * Strongly-typed Data Transfer Object for building an SSLCommerz payment request.
 *
 * Mirrors the full SSLCommerz v4 API parameter set.
 * Mandatory fields are enforced in SslcommerzPaymentService::initiatePayment().
 *
 * @see https://developer.sslcommerz.com/doc/v4/
 */
class PaymentData
{
    // ── Primary / Order ──────────────────────────────────────────────────────
    public string $tran_id;
    public float|string $total_amount;
    public string $currency;

    // ── Redirect URLs ────────────────────────────────────────────────────────
    public ?string $success_url = null;
    public ?string $fail_url    = null;
    public ?string $cancel_url  = null;
    public ?string $ipn_url     = null;

    // ── Customer Information ─────────────────────────────────────────────────
    public string $cus_name;
    public string $cus_email;
    public string $cus_phone;
    public ?string $cus_add1     = null;
    public ?string $cus_add2     = null;
    public ?string $cus_city     = null;
    public ?string $cus_state    = null;
    public ?string $cus_postcode = null;
    public ?string $cus_country  = null;
    public ?string $cus_fax      = null;

    // ── Product / Order ───────────────────────────────────────────────────────
    public string $product_name;
    public string $product_category = 'general';
    public string $product_profile  = 'general';

    // ── Shipping ─────────────────────────────────────────────────────────────
    public string  $shipping_method = 'NO';   // YES / NO / Courier
    public int     $num_of_item     = 1;
    public float   $weight_of_items = 0;
    public float   $logistic_pickup_id   = 0;
    public float   $logistic_delivery_type = 0;
    public ?string $ship_name      = null;
    public ?string $ship_add1      = null;
    public ?string $ship_add2      = null;
    public ?string $ship_city      = null;
    public ?string $ship_state     = null;
    public ?string $ship_postcode  = null;
    public ?string $ship_country   = null;

    // ── Extra Parameters ─────────────────────────────────────────────────────
    public ?string $value_a = null;   // custom pass-through fields
    public ?string $value_b = null;
    public ?string $value_c = null;
    public ?string $value_d = null;

    // ── EMI ──────────────────────────────────────────────────────────────────
    public int     $emi_option     = 0;   // 1 = enabled
    public ?int    $emi_max_inst_option = null;
    public ?int    $emi_selected_inst   = null;
    public ?int    $emi_allow_only      = null;

    // ── BIN Restriction ──────────────────────────────────────────────────────
    public ?string $allowed_bin = null;   // comma-separated BIN list

    // ── Multi-card ───────────────────────────────────────────────────────────
    public ?string $cart          = null;  // JSON cart array
    public ?string $product_amount = null;
    public ?string $discount_amount = null;
    public ?string $convenience_fee = null;

    /**
     * Convert the DTO to the flat array expected by the SSLCommerz API.
     * Null values are omitted to avoid polluting the request body.
     */
    public function toArray(): array
    {
        $data = [
            'store_id'         => config('sslcommerz.store_id'),
            'store_passwd'     => config('sslcommerz.store_password'),
            'tran_id'          => $this->tran_id,
            'total_amount'     => number_format((float) $this->total_amount, 2, '.', ''),
            'currency'         => $this->currency ?? config('sslcommerz.currency', 'BDT'),

            'success_url'      => $this->success_url ?? config('sslcommerz.success_url'),
            'fail_url'         => $this->fail_url    ?? config('sslcommerz.fail_url'),
            'cancel_url'       => $this->cancel_url  ?? config('sslcommerz.cancel_url'),
            'ipn_url'          => $this->ipn_url     ?? config('sslcommerz.ipn_url'),

            'cus_name'         => $this->cus_name,
            'cus_email'        => $this->cus_email,
            'cus_phone'        => $this->cus_phone,

            'product_name'     => $this->product_name,
            'product_category' => $this->product_category,
            'product_profile'  => $this->product_profile,

            'shipping_method'  => $this->shipping_method,
            'num_of_item'      => $this->num_of_item,

            'emi_option'       => $this->emi_option,
        ];

        // Optional customer fields
        foreach (['cus_add1','cus_add2','cus_city','cus_state','cus_postcode','cus_country','cus_fax'] as $field) {
            if ($this->$field !== null) {
                $data[$field] = $this->$field;
            }
        }

        // Optional shipping fields
        foreach (['ship_name','ship_add1','ship_add2','ship_city','ship_state','ship_postcode','ship_country'] as $field) {
            if ($this->$field !== null) {
                $data[$field] = $this->$field;
            }
        }

        // Optional pass-through values
        foreach (['value_a','value_b','value_c','value_d'] as $field) {
            if ($this->$field !== null) {
                $data[$field] = $this->$field;
            }
        }

        // EMI
        foreach (['emi_max_inst_option','emi_selected_inst','emi_allow_only'] as $field) {
            if ($this->$field !== null) {
                $data[$field] = $this->$field;
            }
        }

        // BIN restriction
        if ($this->allowed_bin !== null) {
            $data['allowed_bin'] = $this->allowed_bin;
        }

        // Cart / multi-product
        foreach (['cart','product_amount','discount_amount','convenience_fee'] as $field) {
            if ($this->$field !== null) {
                $data[$field] = $this->$field;
            }
        }

        return $data;
    }
}
