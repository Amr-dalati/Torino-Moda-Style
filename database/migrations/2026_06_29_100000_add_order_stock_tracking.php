<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks per-order warehouse stock allocations for reserve / commit / release lifecycle.
 *
 * JSON allocations avoid a separate table while keeping idempotent release and commit
 * safe under MySQL row locking (InnoDB).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('stock_allocations')->nullable()->after('last_sync_error');
            $table->timestamp('stock_committed_at')->nullable()->after('stock_allocations');
            $table->timestamp('stock_released_at')->nullable()->after('stock_committed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['stock_allocations', 'stock_committed_at', 'stock_released_at']);
        });
    }
};
