<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'phoenix_id',
        'sku',
        'barcode',
        'color_id',
        'size_id',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function isPhoenixOwned(): bool
    {
        return $this->phoenix_id !== null;
    }
}

