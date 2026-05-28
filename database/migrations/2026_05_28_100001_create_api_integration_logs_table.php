<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_integration_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('correlation_id')->index();
            $table->string('method', 10);
            $table->string('url', 500);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('request_body')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->boolean('is_mock')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_integration_logs');
    }
};
