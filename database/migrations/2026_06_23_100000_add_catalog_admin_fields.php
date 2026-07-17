<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('source', 20)->default('manual')->after('is_active');
            $table->boolean('is_visible')->default(true)->after('source');
            $table->boolean('is_featured')->default(false)->after('is_visible');
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
            $table->text('description_ar')->nullable()->after('name_en');
            $table->text('description_en')->nullable()->after('description_ar');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('name_en');
            $table->string('image_disk', 20)->default('public')->after('image_path');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->string('name_en')->nullable()->after('name_ar');
            $table->string('logo_path')->nullable()->after('name_en');
            $table->string('logo_disk', 20)->default('public')->after('logo_path');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path');
            $table->string('disk', 20)->default('public');
            $table->string('alt_text')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
            $table->index(['product_id', 'is_primary']);
        });

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'phoenix_id')) {
            DB::table('products')
                ->whereNotNull('phoenix_id')
                ->update(['source' => 'phoenix']);
        }

        if (Schema::hasTable('brands') && Schema::hasColumn('brands', 'name')) {
            DB::table('brands')
                ->whereNull('name_en')
                ->whereNotNull('name')
                ->update(['name_en' => DB::raw('name')]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'name_en', 'logo_path', 'logo_disk']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_disk']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'source',
                'is_visible',
                'is_featured',
                'sort_order',
                'description_ar',
                'description_en',
            ]);
        });
    }
};
