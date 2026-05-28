<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('sales')->after('password');
            $table->string('phone', 30)->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('phone');
            $table->unsignedBigInteger('warehouse_id')->nullable()->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'phone', 'is_active', 'warehouse_id', 'last_login_at']);
        });
    }
};
