<?php

namespace App\Support;

class InventoryStatus
{
    public static function lowStockThreshold(): int
    {
        return max(0, (int) config('inventory.low_stock_threshold', 5));
    }

    public static function availableQuantity(float $onHand, float $reserved): float
    {
        return max(0.0, $onHand - $reserved);
    }

    /**
     * @return 'out_of_stock'|'low_stock'|'in_stock'|'fully_reserved'
     */
    public static function status(float $onHand, float $reserved): string
    {
        $available = self::availableQuantity($onHand, $reserved);

        if ($onHand > 0 && $available <= 0) {
            return 'fully_reserved';
        }

        if ($available <= 0) {
            return 'out_of_stock';
        }

        if ($available <= self::lowStockThreshold()) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * @return array{label: string, color: string}
     */
    public static function badge(float $onHand, float $reserved): array
    {
        return match (self::status($onHand, $reserved)) {
            'out_of_stock' => ['label' => 'Out of stock', 'color' => 'danger'],
            'low_stock' => ['label' => 'Low stock', 'color' => 'warning'],
            'fully_reserved' => ['label' => 'Fully reserved', 'color' => 'gray'],
            default => ['label' => 'In stock', 'color' => 'success'],
        };
    }
}
