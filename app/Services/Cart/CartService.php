<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getActiveCart(Customer $customer): Cart
    {
        /** @var Cart $cart */
        $cart = Cart::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'status' => 'active'],
            ['subtotal' => '0.00'],
        );

        return $this->loadCart($cart);
    }

    public function addItem(Customer $customer, int $variantId, int $quantity): Cart
    {
        return DB::transaction(function () use ($customer, $variantId, $quantity) {
            $cart = Cart::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'status' => 'active'],
                ['subtotal' => '0.00'],
            );

            $variant = $this->loadVariantOrFail($variantId);

            /** @var CartItem|null $existing */
            $existing = $cart->items()->where('product_variant_id', $variant->id)->first();
            $newQty = $existing ? ($existing->quantity + $quantity) : $quantity;

            $available = $this->availableQuantityForVariant($variant->id);
            $this->ensureSufficientStock($newQty, $available);

            $unitPrice = $this->unitPriceSnapshot($variant);
            $lineTotal = Money::mul($unitPrice, $newQty);

            if ($existing) {
                $existing->forceFill([
                    'quantity' => $newQty,
                    'unit_price_snapshot' => $unitPrice,
                    'line_total' => $lineTotal,
                ])->save();
            } else {
                $cart->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $newQty,
                    'unit_price_snapshot' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);
            }

            $this->recalculateSubtotal($cart);

            return $this->loadCart($cart->fresh());
        });
    }

    public function updateItemQuantity(Customer $customer, int $itemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($customer, $itemId, $quantity) {
            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->firstOrFail();

            /** @var CartItem $item */
            $item = $cart->items()->whereKey($itemId)->firstOrFail();

            $variant = $this->loadVariantOrFail($item->product_variant_id);

            $available = $this->availableQuantityForVariant($variant->id);
            $this->ensureSufficientStock($quantity, $available);

            $unitPrice = $this->unitPriceSnapshot($variant);
            $lineTotal = Money::mul($unitPrice, $quantity);

            $item->forceFill([
                'quantity' => $quantity,
                'unit_price_snapshot' => $unitPrice,
                'line_total' => $lineTotal,
            ])->save();

            $this->recalculateSubtotal($cart);

            return $this->loadCart($cart->fresh());
        });
    }

    public function removeItem(Customer $customer, int $itemId): Cart
    {
        return DB::transaction(function () use ($customer, $itemId) {
            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->firstOrFail();

            /** @var CartItem $item */
            $item = $cart->items()->whereKey($itemId)->firstOrFail();
            $item->delete();

            $this->recalculateSubtotal($cart);

            return $this->loadCart($cart->fresh());
        });
    }

    public function clear(Customer $customer): Cart
    {
        return DB::transaction(function () use ($customer) {
            /** @var Cart $cart */
            $cart = Cart::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'status' => 'active'],
                ['subtotal' => '0.00'],
            );

            $cart->items()->delete();
            $cart->forceFill(['subtotal' => '0.00'])->save();

            return $this->loadCart($cart->fresh());
        });
    }

    protected function loadCart(Cart $cart): Cart
    {
        return $cart->load([
            'items.variant.product',
            'items.variant.color',
            'items.variant.size',
        ]);
    }

    protected function loadVariantOrFail(int $variantId): ProductVariant
    {
        return ProductVariant::query()
            ->with('product')
            ->findOrFail($variantId);
    }

    protected function unitPriceSnapshot(ProductVariant $variant): string
    {
        $variantPrice = $variant->sale_price;
        if ($variantPrice !== null && Money::cents($variantPrice) > 0) {
            return Money::format(Money::cents($variantPrice));
        }

        return Money::format(Money::cents($variant->product->sale_price));
    }

    protected function availableQuantityForVariant(int $variantId): float
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

    protected function ensureSufficientStock(int $requestedQty, float $available): void
    {
        if ($requestedQty < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['Quantity must be at least 1.'],
            ]);
        }

        if ($requestedQty > (int) floor($available)) {
            throw ValidationException::withMessages([
                'quantity' => ['Insufficient stock available.'],
            ]);
        }
    }

    protected function recalculateSubtotal(Cart $cart): void
    {
        $items = $cart->items()->get(['line_total']);
        $sumCents = 0;
        foreach ($items as $item) {
            $sumCents += Money::cents($item->line_total);
        }

        $cart->forceFill(['subtotal' => Money::format($sumCents)])->save();
    }
}

