<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('phoenix_id', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name')->nullable();
            $table->string('phoenix_id', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->string('phoenix_id', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('phoenix_id', 100)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('phoenix_id', 100)->nullable()->unique();
            $table->string('product_code', 100)->index();
            $table->string('barcode', 100)->nullable()->index();
            $table->string('name_ar')->nullable();
            $table->string('name_en')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['product_code']);
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('phoenix_id', 100)->nullable()->unique();
            $table->string('sku', 150)->nullable()->index();
            $table->string('barcode', 100)->nullable()->index();
            $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('sizes')->nullOnDelete();
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'color_id', 'size_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};

