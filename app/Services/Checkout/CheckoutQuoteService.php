<?php

namespace App\Services\Checkout;

use App\Models\Cart;
use App\Models\Customer;
use App\Models\DeliveryArea;
use App\Support\Money;
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

        $deliveryFee = '0.00';

        if ($address->delivery_area_id) {
            /** @var DeliveryArea $area */
            $area = DeliveryArea::query()->with('region')->findOrFail($address->delivery_area_id);
            if (! $area->is_active) {
                throw ValidationException::withMessages([
                    'address_id' => ['Delivery area is inactive.'],
                ]);
            }

            $deliveryFee = Money::format(Money::cents($area->delivery_fee));
        } else {
            throw ValidationException::withMessages([
                'address_id' => ['Delivery area is required.'],
            ]);
        }

        $subtotalCents = 0;
        foreach ($cart->items as $item) {
            $subtotalCents += Money::cents($item->line_total);
        }

        $discountTotal = '0.00';
        $total = Money::format($subtotalCents + Money::cents($deliveryFee) - Money::cents($discountTotal));

        return [
            'subtotal' => Money::format($subtotalCents),
            'delivery_fee' => $deliveryFee,
            'discount_total' => $discountTotal,
            'total' => $total,
            // For debugging / future display, we can return codes later if desired.
        ];
    }
}

