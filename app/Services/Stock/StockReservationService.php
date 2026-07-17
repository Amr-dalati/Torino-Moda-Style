<?php

namespace App\Services\Stock;

use App\Models\Order;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StockReservationService
{
    /**
     * Sum of sellable units across warehouses for a variant.
     */
    public function availableQuantityForVariant(int $variantId): float
    {
        $rows = StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->get(['quantity_on_hand', 'quantity_reserved']);

        $sum = 0.0;
        foreach ($rows as $row) {
            $sum += (float) $row->quantity_on_hand - (float) $row->quantity_reserved;
        }

        return max(0.0, $sum);
    }

    /**
     * Reserve stock for all order lines. Idempotent when allocations already exist.
     *
     * Lifecycle: checkout creates order items, then calls this before payment starts.
     * Rolls back with the surrounding transaction if allocation fails.
     */
    public function reserveForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->stock_allocations !== null) {
                return;
            }

            $order->loadMissing('items');

            if ($order->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'order' => ['Order has no items to reserve stock for.'],
                ]);
            }

            $allocations = [];

            foreach ($order->items as $item) {
                $remaining = (int) $item->quantity;
                $variantAllocations = $this->allocateVariantQuantity(
                    $item->product_variant_id,
                    $remaining,
                );

                foreach ($variantAllocations as $allocation) {
                    $allocations[] = $allocation;
                }
            }

            $order->forceFill(['stock_allocations' => $allocations])->save();
        });
    }

    /**
     * Commit reserved stock after successful payment: decrement on-hand and reserved.
     * Idempotent when stock_committed_at is already set.
     */
    public function commitForPaidOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->stock_committed_at !== null) {
                return;
            }

            if ($order->stock_released_at !== null) {
                throw new RuntimeException('Cannot commit stock for an order whose reservation was released.');
            }

            $allocations = $order->stock_allocations;
            if (! is_array($allocations) || $allocations === []) {
                throw new RuntimeException('Cannot commit stock without prior reservation allocations.');
            }

            foreach ($allocations as $allocation) {
                $this->applyCommitAllocation($allocation);
            }

            $order->forceFill(['stock_committed_at' => now()])->save();
        });
    }

    /**
     * Release a pending reservation (payment failed, expired, or unpaid order cancelled).
     * Does nothing once stock is committed (paid) or already released.
     */
    public function releaseForOrder(Order $order): void
    {
        DB::transaction(function () use ($order) {
            /** @var Order $order */
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($order->stock_committed_at !== null) {
                // Paid orders sold stock; restock requires an explicit refund/restock flow.
                return;
            }

            if ($order->stock_released_at !== null) {
                return;
            }

            $allocations = $order->stock_allocations;
            if (! is_array($allocations) || $allocations === []) {
                return;
            }

            foreach ($allocations as $allocation) {
                $this->applyReleaseAllocation($allocation);
            }

            $order->forceFill(['stock_released_at' => now()])->save();
        });
    }

    /**
     * @return list<array{product_variant_id: int, warehouse_id: int, quantity: int}>
     */
    protected function allocateVariantQuantity(int $variantId, int $requestedQty): array
    {
        if ($requestedQty < 1) {
            throw ValidationException::withMessages([
                'cart' => ['Invalid quantity for stock reservation.'],
            ]);
        }

        $remaining = $requestedQty;
        $allocations = [];

        /** @var \Illuminate\Database\Eloquent\Collection<int, StockLevel> $levels */
        $levels = StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->orderBy('warehouse_id')
            ->lockForUpdate()
            ->get();

        foreach ($levels as $level) {
            if ($remaining <= 0) {
                break;
            }

            $available = max(0.0, (float) $level->quantity_on_hand - (float) $level->quantity_reserved);
            $take = min($remaining, (int) floor($available));

            if ($take <= 0) {
                continue;
            }

            $level->forceFill([
                'quantity_reserved' => (float) $level->quantity_reserved + $take,
            ])->save();

            $allocations[] = [
                'product_variant_id' => $variantId,
                'warehouse_id' => (int) $level->warehouse_id,
                'quantity' => $take,
            ];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'cart' => ['Insufficient stock available for one or more items.'],
            ]);
        }

        return $allocations;
    }

    /**
     * @param  array{product_variant_id: int, warehouse_id: int, quantity: int}  $allocation
     */
    protected function applyCommitAllocation(array $allocation): void
    {
        $level = $this->lockStockLevel(
            (int) $allocation['product_variant_id'],
            (int) $allocation['warehouse_id'],
        );

        $qty = (int) $allocation['quantity'];

        if ((float) $level->quantity_reserved < $qty || (float) $level->quantity_on_hand < $qty) {
            throw new RuntimeException('Stock level inconsistent during commit.');
        }

        $level->forceFill([
            'quantity_on_hand' => (float) $level->quantity_on_hand - $qty,
            'quantity_reserved' => (float) $level->quantity_reserved - $qty,
        ])->save();
    }

    /**
     * @param  array{product_variant_id: int, warehouse_id: int, quantity: int}  $allocation
     */
    protected function applyReleaseAllocation(array $allocation): void
    {
        $level = $this->lockStockLevel(
            (int) $allocation['product_variant_id'],
            (int) $allocation['warehouse_id'],
        );

        $qty = (int) $allocation['quantity'];
        $newReserved = max(0.0, (float) $level->quantity_reserved - $qty);

        $level->forceFill(['quantity_reserved' => $newReserved])->save();
    }

    protected function lockStockLevel(int $variantId, int $warehouseId): StockLevel
    {
        /** @var StockLevel|null $level */
        $level = StockLevel::query()
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if (! $level) {
            throw new RuntimeException('Stock level missing for committed allocation.');
        }

        return $level;
    }
}
