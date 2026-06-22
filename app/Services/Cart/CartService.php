<?php

namespace App\Services\Cart;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getActiveCart(Customer $customer): Cart
    {
        return DB::transaction(function () use ($customer) {
            // Serialize active-cart creation per customer.
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            /** @var Cart|null $cart */
            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                $cart = Cart::query()->create([
                    'customer_id' => $customer->id,
                    'status' => 'active',
                    'subtotal' => '0.00',
                    'currency' => config('app.currency'),
                ]);
            }

            return $this->loadCart($cart->fresh());
        });
    }

    public function addItem(Customer $customer, int $variantId, int $quantity): Cart
    {
        return DB::transaction(function () use ($customer, $variantId, $quantity) {
            // Serialize active-cart/item mutations per customer.
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            /** @var Cart $cart */
            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                $cart = Cart::query()->create([
                    'customer_id' => $customer->id,
                    'status' => 'active',
                    'subtotal' => '0.00',
                    'currency' => config('app.currency'),
                ]);
            }

            $variant = $this->loadVariantOrFail($variantId);

            /** @var CartItem|null $existing */
            $existing = $cart->items()
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();

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
                try {
                    $cart->items()->create([
                        'product_variant_id' => $variant->id,
                        'quantity' => $newQty,
                        'unit_price_snapshot' => $unitPrice,
                        'line_total' => $lineTotal,
                    ]);
                } catch (QueryException $e) {
                    // Handle race where a concurrent request created the same (cart_id, product_variant_id).
                    if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'unique')) {
                        /** @var CartItem $existingAfter */
                        $existingAfter = $cart->items()
                            ->where('product_variant_id', $variant->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        $mergedQty = $existingAfter->quantity + $quantity;
                        $available = $this->availableQuantityForVariant($variant->id);
                        $this->ensureSufficientStock($mergedQty, $available);

                        $existingAfter->forceFill([
                            'quantity' => $mergedQty,
                            'unit_price_snapshot' => $unitPrice,
                            'line_total' => Money::mul($unitPrice, $mergedQty),
                        ])->save();
                    } else {
                        throw $e;
                    }
                }
            }

            $this->recalculateSubtotal($cart);

            return $this->loadCart($cart->fresh());
        });
    }

    public function updateItemQuantity(Customer $customer, int $itemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($customer, $itemId, $quantity) {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            /** @var CartItem $item */
            $item = $cart->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();

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
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            /** @var CartItem $item */
            $item = $cart->items()->whereKey($itemId)->lockForUpdate()->firstOrFail();
            $item->delete();

            $this->recalculateSubtotal($cart);

            return $this->loadCart($cart->fresh());
        });
    }

    public function clear(Customer $customer): Cart
    {
        return DB::transaction(function () use ($customer) {
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            /** @var Cart|null $cart */
            $cart = Cart::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if (! $cart) {
                $cart = Cart::query()->create([
                    'customer_id' => $customer->id,
                    'status' => 'active',
                    'subtotal' => '0.00',
                    'currency' => config('app.currency'),
                ]);
            }

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

