<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->index();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamp('anonymized_at')->nullable();
            $table->string('deletion_reason', 500)->nullable();
            $table->index(['is_active', 'deleted_at'], 'customers_active_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_active_deleted_idx');
            $table->dropColumn([
                'deletion_requested_at',
                'deleted_at',
                'anonymized_at',
                'deletion_reason',
            ]);
        });
    }
};
