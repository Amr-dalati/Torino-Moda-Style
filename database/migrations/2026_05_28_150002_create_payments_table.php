<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('provider', 30)->default('mock');
            $table->string('method', 30)->default('card');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->nullable();
            $table->string('status', 30)->default('pending')->index();

            $table->string('merchant_reference', 100)->unique()->index();
            $table->string('gateway_payment_id', 100)->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 50)->nullable();
            $table->string('failure_message', 255)->nullable();
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

