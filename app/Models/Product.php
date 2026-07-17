<?php

namespace App\Models;

use App\Enums\ProductSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = [
        'phoenix_id',
        'product_code',
        'barcode',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'category_id',
        'brand_id',
        'sale_price',
        'is_active',
        'source',
        'is_visible',
        'is_featured',
        'sort_order',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_visible' => 'boolean',
            'is_featured' => 'boolean',
            'source' => ProductSource::class,
            'synced_at' => 'datetime',
        ];
    }

    public function isPhoenixOwned(): bool
    {
        return $this->source === ProductSource::Phoenix || $this->phoenix_id !== null;
    }

    public function scopeStorefrontVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_visible', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }
}
