<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\DeliveryArea;
use Illuminate\Validation\ValidationException;

class CheckoutQuoteService
{
    /**
     * @return array{subtotal: string, delivery_fee: string, discount_total: string, total: string}
     */
    public function quote(Customer $customer, int $addressId): array
    {
        /** @var Cart $cart */
        $cart = Cart::query()->firstOrCreate(
            ['customer_id' => $customer->id, 'status' => 'active'],
            ['subtotal' => 0],
        );

        $cart->load(['items']);

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Cart is empty.'],
            ]);
        }

        $address = $customer->addresses()->whereKey($addressId)->firstOrFail();

        $deliveryFee = 0.0;
        $deliveryAreaCode = null;
        $deliveryRegionCode = null;

        if ($address->delivery_area_id) {
            /** @var DeliveryArea $area */
            $area = DeliveryArea::query()->with('region')->findOrFail($address->delivery_area_id);
            if (! $area->is_active) {
                throw ValidationException::withMessages([
                    'address_id' => ['Delivery area is inactive.'],
                ]);
            }

            $deliveryFee = (float) $area->delivery_fee;
            $deliveryAreaCode = $area->code;
            $deliveryRegionCode = $area->region?->code;
        } else {
            throw ValidationException::withMessages([
                'address_id' => ['Delivery area is required.'],
            ]);
        }

        $subtotal = (float) $cart->items->sum('line_total');
        $discountTotal = 0.0;
        $total = $subtotal + $deliveryFee - $discountTotal;

        return [
            'subtotal' => $this->formatMoney($subtotal),
            'delivery_fee' => $this->formatMoney($deliveryFee),
            'discount_total' => $this->formatMoney($discountTotal),
            'total' => $this->formatMoney($total),
            // For debugging / future display, we can return codes later if desired.
        ];
    }

    protected function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}

