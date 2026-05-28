<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('phoenix_id', 100)->nullable()->unique();
            $table->string('code', 50)->unique();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 12, 3)->default(0);
            $table->decimal('quantity_reserved', 12, 3)->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['product_variant_id', 'warehouse_id']);
            $table->index(['warehouse_id']);
            $table->index(['product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('warehouses');
    }
};

