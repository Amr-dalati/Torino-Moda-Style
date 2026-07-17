<?php

namespace App\Services\Stock;

use App\Enums\StockAdjustmentType;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockAdjustmentService
{
    public function adjust(
        int $stockLevelId,
        StockAdjustmentType $type,
        float $quantity,
        string $reason,
        ?string $reference,
        User $user,
    ): StockLevel {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => ['A reason is required for stock adjustments.'],
            ]);
        }

        if ($quantity < 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be zero or greater.'],
            ]);
        }

        if ($type !== StockAdjustmentType::Set && $quantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be greater than zero for increase or decrease adjustments.'],
            ]);
        }

        return DB::transaction(function () use ($stockLevelId, $type, $quantity, $reason, $reference, $user) {
            /** @var StockLevel $level */
            $level = StockLevel::query()->whereKey($stockLevelId)->lockForUpdate()->firstOrFail();

            $before = (float) $level->quantity_on_hand;
            $reserved = (float) $level->quantity_reserved;

            $after = match ($type) {
                StockAdjustmentType::Increase => $before + $quantity,
                StockAdjustmentType::Decrease => $before - $quantity,
                StockAdjustmentType::Set => $quantity,
            };

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity' => ['Quantity on hand cannot be negative.'],
                ]);
            }

            if ($after < $reserved) {
                throw ValidationException::withMessages([
                    'quantity' => ['Quantity on hand cannot fall below reserved quantity.'],
                ]);
            }

            $change = $after - $before;

            $level->forceFill(['quantity_on_hand' => $after])->save();

            StockAdjustment::query()->create([
                'stock_level_id' => $level->id,
                'product_variant_id' => $level->product_variant_id,
                'warehouse_id' => $level->warehouse_id,
                'user_id' => $user->id,
                'adjustment_type' => $type,
                'quantity_before' => $before,
                'quantity_change' => $change,
                'quantity_after' => $after,
                'reason' => $reason,
                'reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null,
            ]);

            return $level->fresh(['variant.product', 'variant.color', 'variant.size', 'warehouse']);
        });
    }
}
