<?php

namespace App\Models;

use App\Enums\StockAdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustment extends Model
{
    protected $fillable = [
        'stock_level_id',
        'product_variant_id',
        'warehouse_id',
        'user_id',
        'adjustment_type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'reason',
        'reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_type' => StockAdjustmentType::class,
            'quantity_before' => 'decimal:3',
            'quantity_change' => 'decimal:3',
            'quantity_after' => 'decimal:3',
            'metadata' => 'array',
        ];
    }

    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
