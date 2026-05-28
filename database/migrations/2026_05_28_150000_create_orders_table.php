<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique()->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('order_status', 30)->default('awaiting_payment')->index();
            $table->string('payment_status', 30)->default('pending')->index();

            $table->decimal('subtotal', 12, 2);
            $table->decimal('delivery_fee', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency', 10)->nullable();

            $table->string('shipping_label', 50)->nullable();
            $table->string('shipping_recipient_name')->nullable();
            $table->string('shipping_recipient_phone', 30)->nullable();
            $table->string('shipping_address_line1');
            $table->string('shipping_address_line2')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_area_name')->nullable();
            $table->string('shipping_postal_code', 30)->nullable();
            $table->string('shipping_delivery_region_code', 50)->nullable();
            $table->string('shipping_delivery_area_code', 50)->nullable();
            $table->foreignId('shipping_delivery_area_id')->nullable()->constrained('delivery_areas')->nullOnDelete();

            $table->foreignId('customer_address_id')->nullable()->constrained('customer_addresses')->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained('carts')->nullOnDelete();

            $table->string('phoenix_order_id', 100)->nullable()->index();
            $table->string('sync_status', 20)->nullable()->index();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->text('last_sync_error')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

