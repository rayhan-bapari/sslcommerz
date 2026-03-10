<?php

namespace RayhanBapari\SslcommerzPayment;

use Illuminate\Support\ServiceProvider;
use RayhanBapari\SslcommerzPayment\Contracts\SslcommerzPaymentInterface;
use RayhanBapari\SslcommerzPayment\Services\SslcommerzIpnService;
use RayhanBapari\SslcommerzPayment\Services\SslcommerzPaymentService;

class SslcommerzPaymentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ── Publishable assets ────────────────────────────────────────────────
        $this->publishes([
            __DIR__ . '/Config/sslcommerz.php' => config_path('sslcommerz.php'),
        ], 'sslcommerz-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'sslcommerz-migrations');

        // ── Auto-load migrations ──────────────────────────────────────────────
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // ── IPN Route ─────────────────────────────────────────────────────────
        if (config('sslcommerz.ipn.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/ipn.php');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/sslcommerz.php', 'sslcommerz');

        // Bind interface
        $this->app->bind(SslcommerzPaymentInterface::class, SslcommerzPaymentService::class);

        // Singleton payment service
        $this->app->singleton('sslcommerz', fn($app) => $app->make(SslcommerzPaymentService::class));

        // Singleton IPN service
        $this->app->singleton(SslcommerzIpnService::class);
    }

    public function provides(): array
    {
        return [
            'sslcommerz',
            SslcommerzPaymentInterface::class,
            SslcommerzIpnService::class,
        ];
    }
}
