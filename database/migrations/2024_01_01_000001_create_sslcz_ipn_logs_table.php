<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sslcz_ipn_logs')) {
            Schema::create('sslcz_ipn_logs', function (Blueprint $t) {
                $t->id();
                $t->string('tran_id')->nullable()->index()
                  ->comment('Merchant transaction ID');
                $t->string('val_id')->nullable()->index()
                  ->comment('SSLCommerz validation ID');
                $t->string('status', 50)->nullable()
                  ->comment('VALID | VALIDATED | INVALID_TRANSACTION | FAILED | CANCELLED');
                $t->string('amount')->nullable()
                  ->comment('Transaction amount sent by merchant');
                $t->string('currency', 10)->nullable();
                $t->string('bank_tran_id')->nullable()->index()
                  ->comment('Bank/gateway transaction ID — use this for refunds');
                $t->longText('raw_payload')
                  ->comment('Full raw POST data from SSLCommerz');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sslcz_ipn_logs');
    }
};
