<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_region_id')->constrained('delivery_regions')->cascadeOnDelete();
            $table->string('code', 50)->unique()->index();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_areas');
    }
};

