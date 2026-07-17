<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockLevel extends Model
{
    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'quantity_on_hand',
        'quantity_reserved',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'decimal:3',
            'quantity_reserved' => 'decimal:3',
            'synced_at' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(StockAdjustment::class);
    }

    public function availableQuantity(): float
    {
        return max(0.0, (float) $this->quantity_on_hand - (float) $this->quantity_reserved);
    }
}

