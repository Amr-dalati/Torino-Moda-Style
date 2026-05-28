<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\DeliveryArea;
use Illuminate\Validation\ValidationException;

class CheckoutQuoteService
{
    /**
     * @return array{subtotal: float, delivery_fee: float, discount_total: float, total: float}
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
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'discount_total' => $discountTotal,
            'total' => $total,
            // For debugging / future display, we can return codes later if desired.
        ];
    }
}

