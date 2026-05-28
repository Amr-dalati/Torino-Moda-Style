<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();

            $table->string('product_code', 100)->nullable();
            $table->string('variant_sku', 150)->nullable();
            $table->string('variant_barcode', 100)->nullable();
            $table->string('product_name_en')->nullable();
            $table->string('product_name_ar')->nullable();
            $table->string('color_code', 50)->nullable();
            $table->string('size_code', 50)->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price_snapshot', 12, 2);
            $table->decimal('line_total', 12, 2);

            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['product_variant_id']);
            $table->index(['order_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

