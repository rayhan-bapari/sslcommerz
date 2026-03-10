<?php

namespace RayhanBapari\SslcommerzPayment\Tests\Unit;

use Orchestra\Testbench\TestCase;
use RayhanBapari\SslcommerzPayment\DTOs\PaymentData;
use RayhanBapari\SslcommerzPayment\DTOs\PaymentResponse;
use RayhanBapari\SslcommerzPayment\Exceptions\SslcommerzException;
use RayhanBapari\SslcommerzPayment\Facades\Sslcommerz;
use RayhanBapari\SslcommerzPayment\Services\SslcommerzPaymentService;
use RayhanBapari\SslcommerzPayment\SslcommerzPaymentServiceProvider;

class SslcommerzPaymentServiceTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SslcommerzPaymentServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Sslcommerz' => Sslcommerz::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sslcommerz.sandbox', true);
        $app['config']->set('sslcommerz.store_id', 'testbox');
        $app['config']->set('sslcommerz.store_password', 'qwerty');
        $app['config']->set('sslcommerz.currency', 'BDT');
    }

    // ── Container / Service ───────────────────────────────────────────────────

    public function test_service_is_bound_in_container(): void
    {
        $service = $this->app->make('sslcommerz');
        $this->assertInstanceOf(SslcommerzPaymentService::class, $service);
    }

    public function test_facade_resolves_correctly(): void
    {
        $this->assertInstanceOf(SslcommerzPaymentService::class, Sslcommerz::getFacadeRoot());
    }

    // ── Config ────────────────────────────────────────────────────────────────

    public function test_config_values_are_loaded(): void
    {
        $this->assertTrue(config('sslcommerz.sandbox'));
        $this->assertEquals('testbox', config('sslcommerz.store_id'));
        $this->assertEquals('BDT', config('sslcommerz.currency'));
    }

    public function test_sandbox_base_url_is_used_when_sandbox(): void
    {
        $service = $this->app->make(SslcommerzPaymentService::class);
        $prop = new \ReflectionProperty($service, 'baseUrl');
        $prop->setAccessible(true);
        $this->assertStringContainsString('sandbox', $prop->getValue($service));
    }

    // ── PaymentData DTO ───────────────────────────────────────────────────────

    public function test_payment_data_to_array_includes_all_required_fields(): void
    {
        $dto = new PaymentData();
        $dto->tran_id = 'TRX-001';
        $dto->total_amount = 150.0;
        $dto->product_name = 'Test Product';
        $dto->cus_name = 'John Doe';
        $dto->cus_email = 'john@example.com';
        $dto->cus_phone = '01711111111';
        $dto->currency = 'BDT';

        $arr = $dto->toArray();

        $this->assertEquals('TRX-001', $arr['tran_id']);
        $this->assertEquals('150.00', $arr['total_amount']);
        $this->assertEquals('Test Product', $arr['product_name']);
        $this->assertEquals('John Doe', $arr['cus_name']);
        $this->assertEquals('general', $arr['product_category']);
    }

    public function test_payment_data_amount_is_formatted_to_two_decimals(): void
    {
        $dto = new PaymentData();
        $dto->tran_id = 'TRX-X';
        $dto->total_amount = 99.9;
        $dto->product_name = 'Item';
        $dto->cus_name = 'Test';
        $dto->cus_email = 'test@test.com';
        $dto->cus_phone = '0';

        $arr = $dto->toArray();
        $this->assertEquals('99.90', $arr['total_amount']);
    }

    // ── PaymentResponse DTO ───────────────────────────────────────────────────

    public function test_payment_response_success_returns_true_on_SUCCESS(): void
    {
        $response = new PaymentResponse(['status' => 'SUCCESS', 'GatewayPageURL' => 'https://gateway.url']);
        $this->assertTrue($response->success());
        $this->assertEquals('https://gateway.url', $response->gatewayPageURL());
    }

    public function test_payment_response_success_returns_false_on_FAIL(): void
    {
        $response = new PaymentResponse(['status' => 'FAILED', 'failedreason' => 'Invalid store']);
        $this->assertFalse($response->success());
        $this->assertNull($response->gatewayPageURL());
    }

    public function test_payment_response_get_returns_default_for_missing_key(): void
    {
        $response = new PaymentResponse(['status' => 'SUCCESS']);
        $this->assertEquals('fallback', $response->get('nonexistent', 'fallback'));
    }

    // ── IPN Hash Verification ─────────────────────────────────────────────────

    public function test_verify_ipn_hash_returns_false_when_keys_missing(): void
    {
        $service = $this->app->make(SslcommerzPaymentService::class);
        $this->assertFalse($service->verifyIpnHash([]));
        $this->assertFalse($service->verifyIpnHash(['verify_sign' => 'abc']));
        $this->assertFalse($service->verifyIpnHash(['verify_key' => 'tran_id']));
    }

    public function test_verify_ipn_hash_validates_correctly(): void
    {
        $storePassword = 'qwerty';
        $postData = [
            'tran_id' => 'TRX-123',
            'amount' => '100.00',
            'currency' => 'BDT',
            'verify_key' => 'tran_id,amount,currency',
        ];

        // Build the expected hash the same way the service does
        $parts = ['tran_id=TRX-123', 'amount=100.00', 'currency=BDT', 'store_passwd=' . md5($storePassword)];
        $postData['verify_sign'] = md5(implode('&', $parts));

        $service = $this->app->make(SslcommerzPaymentService::class);
        $this->assertTrue($service->verifyIpnHash($postData));
    }

    public function test_verify_ipn_hash_rejects_tampered_data(): void
    {
        $storePassword = 'qwerty';
        $postData = [
            'tran_id' => 'TRX-123',
            'amount' => '100.00',
            'currency' => 'BDT',
            'verify_key' => 'tran_id,amount,currency',
        ];

        $parts = ['tran_id=TRX-123', 'amount=100.00', 'currency=BDT', 'store_passwd=' . md5($storePassword)];
        $postData['verify_sign'] = md5(implode('&', $parts));

        // Tamper with the amount after signing
        $postData['amount'] = '999.00';

        $service = $this->app->make(SslcommerzPaymentService::class);
        $this->assertFalse($service->verifyIpnHash($postData));
    }

    // ── Missing credentials ───────────────────────────────────────────────────

    public function test_initiate_payment_throws_when_credentials_missing(): void
    {
        $this->expectException(SslcommerzException::class);
        $this->expectExceptionMessageMatches('/store_id|store_password/');

        $this->app['config']->set('sslcommerz.store_id', '');

        $service = new SslcommerzPaymentService();
        $dto = new PaymentData();
        $dto->tran_id = 'T1';
        $dto->total_amount = 10;
        $dto->product_name = 'P';
        $dto->cus_name = 'N';
        $dto->cus_email = 'e@e.com';
        $dto->cus_phone = '0';
        $dto->success_url = 'http://a.b/s';
        $dto->fail_url = 'http://a.b/f';
        $dto->cancel_url = 'http://a.b/c';

        $service->initiatePayment($dto);
    }

    // ── makePaymentData helper ────────────────────────────────────────────────

    public function test_make_payment_data_returns_populated_dto(): void
    {
        $service = $this->app->make(SslcommerzPaymentService::class);
        $dto = $service->makePaymentData('T999', 250.0, 'Book', 'Alice', 'alice@mail.com', '01700000000');

        $this->assertInstanceOf(PaymentData::class, $dto);
        $this->assertEquals('T999', $dto->tran_id);
        $this->assertEquals(250.0, $dto->total_amount);
        $this->assertEquals('alice@mail.com', $dto->cus_email);
    }
}
