<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('delivery_area_id')->nullable()->constrained('delivery_areas')->nullOnDelete();
            $table->string('label', 50)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone', 30)->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('area_name')->nullable();
            $table->string('postal_code', 30)->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->index(['customer_id']);
            $table->index(['delivery_area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};

