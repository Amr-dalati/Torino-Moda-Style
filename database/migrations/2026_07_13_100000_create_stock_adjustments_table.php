<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_level_id')->constrained('stock_levels')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('adjustment_type', 20);
            $table->decimal('quantity_before', 12, 3);
            $table->decimal('quantity_change', 12, 3);
            $table->decimal('quantity_after', 12, 3);
            $table->string('reason');
            $table->string('reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['stock_level_id', 'created_at']);
            $table->index(['product_variant_id', 'created_at']);
            $table->index(['warehouse_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
