<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->index(['customer_id', 'is_default'], 'customer_addresses_customer_default_idx');
        });

        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->index(['delivery_region_id', 'is_active'], 'delivery_areas_region_active_idx');
        });

        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->index(['provider', 'processing_status', 'received_at'], 'payment_webhooks_provider_status_received_idx');
        });

        Schema::table('api_integration_logs', function (Blueprint $table) {
            $table->index(['status_code', 'created_at'], 'api_integration_logs_status_created_idx');
        });

        Schema::table('phoenix_sync_logs', function (Blueprint $table) {
            $table->index(['sync_type', 'started_at'], 'phoenix_sync_logs_type_started_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropIndex('customer_addresses_customer_default_idx');
        });

        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->dropIndex('delivery_areas_region_active_idx');
        });

        Schema::table('payment_webhooks', function (Blueprint $table) {
            $table->dropIndex('payment_webhooks_provider_status_received_idx');
        });

        Schema::table('api_integration_logs', function (Blueprint $table) {
            $table->dropIndex('api_integration_logs_status_created_idx');
        });

        Schema::table('phoenix_sync_logs', function (Blueprint $table) {
            $table->dropIndex('phoenix_sync_logs_type_started_idx');
        });
    }
};

