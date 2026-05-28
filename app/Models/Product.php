<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'phoenix_id',
        'product_code',
        'barcode',
        'name_ar',
        'name_en',
        'category_id',
        'brand_id',
        'sale_price',
        'is_active',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
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
}

