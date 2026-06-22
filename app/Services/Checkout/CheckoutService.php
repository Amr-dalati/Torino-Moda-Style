<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class CheckoutService
{
    public function __construct(
        protected CheckoutQuoteService $quotes,
        protected PaymentService $payments,
    ) {}

    /**
     * @return array{order: Order, payment: Payment}
     */
    public function checkout(Customer $customer, int $addressId): array
    {
        return DB::transaction(function () use ($customer, $addressId) {
            // Serialize checkout per customer to prevent double-submit races.
            Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            /** @var Cart $cart */
            $cart = Cart::query()->firstOrCreate(
                ['customer_id' => $customer->id, 'status' => 'active'],
                ['subtotal' => '0.00', 'currency' => config('app.currency')],
            );

            /** @var Cart $cart */
            $cart = Cart::query()->whereKey($cart->id)->lockForUpdate()->firstOrFail();

            $cart->load(['items', 'items.variant.product', 'items.variant.color', 'items.variant.size']);

            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => ['Cart is empty.'],
                ]);
            }

            $address = $customer->addresses()->whereKey($addressId)->firstOrFail();

            if (! $address->delivery_area_id) {
                throw ValidationException::withMessages([
                    'address_id' => ['Delivery area is required.'],
                ]);
            }

            /** @var DeliveryArea $area */
            $area = DeliveryArea::query()->with('region')->findOrFail($address->delivery_area_id);
            if (! $area->is_active) {
                throw ValidationException::withMessages([
                    'address_id' => ['Delivery area is inactive.'],
                ]);
            }

            // Revalidate stock at checkout time.
            foreach ($cart->items as $item) {
                $available = $this->availableQuantityForVariant($item->product_variant_id);
                if ($item->quantity > (int) floor($available)) {
                    throw ValidationException::withMessages([
                        'cart' => ['Insufficient stock available for one or more items.'],
                    ]);
                }
            }

            // Idempotency: if this cart was already checked out and has an order, return it.
            $existingOrder = Order::query()
                ->where('cart_id', $cart->id)
                ->with(['items', 'payments' => fn ($q) => $q->orderByDesc('id')])
                ->lockForUpdate()
                ->first();

            if ($existingOrder) {
                $latestPayment = $existingOrder->payments->first();
                if (! $latestPayment) {
                    // Shouldn't happen, but keep shape stable.
                    throw ValidationException::withMessages([
                        'order' => ['Checkout already started, but no payment exists.'],
                    ]);
                }

                return [
                    'order' => $existingOrder,
                    'payment' => $latestPayment,
                ];
            }

            $quote = $this->quotes->quote($customer, $addressId);

            try {
                $order = Order::query()->create([
                'order_number' => $this->generateTempOrderNumber(),
                'customer_id' => $customer->id,
                'order_status' => 'awaiting_payment',
                'payment_status' => 'pending',
                'subtotal' => $quote['subtotal'],
                'delivery_fee' => $quote['delivery_fee'],
                'discount_total' => $quote['discount_total'],
                'total' => $quote['total'],
                'currency' => config('app.currency'),
                'shipping_label' => $address->label,
                'shipping_recipient_name' => $address->recipient_name,
                'shipping_recipient_phone' => $address->recipient_phone,
                'shipping_address_line1' => $address->address_line1,
                'shipping_address_line2' => $address->address_line2,
                'shipping_city' => $address->city,
                'shipping_area_name' => $address->area_name,
                'shipping_postal_code' => $address->postal_code,
                'shipping_delivery_region_code' => $area->region?->code,
                'shipping_delivery_area_code' => $area->code,
                'shipping_delivery_area_id' => $area->id,
                'customer_address_id' => $address->id,
                'cart_id' => $cart->id,
                ]);
            } catch (QueryException $e) {
                // Handle unique(cart_id) race: return existing order.
                if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'unique')) {
                    /** @var Order $order */
                    $order = Order::query()
                        ->where('cart_id', $cart->id)
                        ->with(['items', 'payments' => fn ($q) => $q->orderByDesc('id')])
                        ->lockForUpdate()
                        ->firstOrFail();

                    $latestPayment = $order->payments->firstOrFail();

                    return [
                        'order' => $order,
                        'payment' => $latestPayment,
                    ];
                }

                throw $e;
            }

            // Now replace temp order number with deterministic number using ID.
            $order->forceFill(['order_number' => $this->formatOrderNumber($order->id)])->save();

            foreach ($cart->items as $cartItem) {
                /** @var ProductVariant $variant */
                $variant = $cartItem->variant;

                OrderItem::query()->create([
                    'order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_code' => $variant->product?->product_code,
                    'variant_sku' => $variant->sku,
                    'variant_barcode' => $variant->barcode,
                    'product_name_en' => $variant->product?->name_en,
                    'product_name_ar' => $variant->product?->name_ar,
                    'color_code' => $variant->color?->code,
                    'size_code' => $variant->size?->code,
                    'quantity' => $cartItem->quantity,
                    'unit_price_snapshot' => $cartItem->unit_price_snapshot,
                    'line_total' => $cartItem->line_total,
                ]);
            }

            $payment = Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'mock',
                'method' => 'card',
                'amount' => $order->total,
                'currency' => $order->currency,
                'status' => 'pending',
                'merchant_reference' => $this->merchantReference($order->order_number),
            ]);

            $payment = $this->payments->start($payment);

            return [
                'order' => $order->fresh()->load(['items', 'payments']),
                'payment' => $payment,
            ];
        });
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

    protected function generateTempOrderNumber(): string
    {
        return 'TMP-' . Str::upper(Str::random(12));
    }

    protected function formatOrderNumber(int $id): string
    {
        return 'TMS-' . now()->format('Y') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    protected function merchantReference(string $orderNumber): string
    {
        return 'mr_' . $orderNumber;
    }
}

