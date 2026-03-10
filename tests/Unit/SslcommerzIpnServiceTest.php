<?php

namespace RayhanBapari\SslcommerzPayment\Tests\Unit;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use RayhanBapari\SslcommerzPayment\Exceptions\SslcommerzIpnException;
use RayhanBapari\SslcommerzPayment\Services\SslcommerzIpnService;
use RayhanBapari\SslcommerzPayment\Services\SslcommerzPaymentService;
use RayhanBapari\SslcommerzPayment\SslcommerzPaymentServiceProvider;

class SslcommerzIpnServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SslcommerzPaymentServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sslcommerz.sandbox', true);
        $app['config']->set('sslcommerz.store_id', 'testbox');
        $app['config']->set('sslcommerz.store_password', 'qwerty');
        $app['config']->set('sslcommerz.ipn.log_payloads', false);
    }

    private function buildValidIpnPost(array $overrides = []): array
    {
        $password = 'qwerty';
        $base = array_merge([
            'tran_id'    => 'TRX-IPN-001',
            'val_id'     => 'VAL123',
            'amount'     => '100.00',
            'currency'   => 'BDT',
            'status'     => 'VALID',
            'bank_tran_id' => 'BANK123',
            'card_type'  => 'VISA-Dutch',
            'cus_name'   => 'John',
            'cus_email'  => 'john@test.com',
            'cus_phone'  => '01711111111',
        ], $overrides);

        $base['verify_key']  = implode(',', array_keys($base));
        $parts = [];
        foreach (explode(',', $base['verify_key']) as $k) {
            $parts[] = $k . '=' . $base[$k];
        }
        $parts[] = 'store_passwd=' . md5($password);
        $base['verify_sign'] = md5(implode('&', $parts));

        return $base;
    }

    public function test_empty_payload_throws_exception(): void
    {
        $this->expectException(SslcommerzIpnException::class);

        $service = $this->app->make(SslcommerzIpnService::class);
        $request = Request::create('/sslcommerz/ipn', 'POST', []);
        $service->handle($request);
    }

    public function test_tampered_payload_throws_ipn_exception(): void
    {
        $this->expectException(SslcommerzIpnException::class);

        $data = $this->buildValidIpnPost();
        $data['amount'] = '999.00'; // tamper after signing

        $service = $this->app->make(SslcommerzIpnService::class);
        $request = Request::create('/sslcommerz/ipn', 'POST', $data);
        $service->handle($request);
    }
}
