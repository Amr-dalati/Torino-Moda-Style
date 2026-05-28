<?php

namespace App\Services\Admin;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderFulfillmentService
{
    public function markProcessing(int $orderId): Order
    {
        return $this->transition($orderId, 'processing', ['paid']);
    }

    public function markShipped(int $orderId): Order
    {
        return $this->transition($orderId, 'shipped', ['processing']);
    }

    public function markDelivered(int $orderId): Order
    {
        return $this->transition($orderId, 'delivered', ['shipped']);
    }

    public function cancel(int $orderId): Order
    {
        // Only allow cancellation before shipment.
        return $this->transition($orderId, 'cancelled', ['paid', 'processing']);
    }

    /**
     * @param  list<string>  $allowedFrom
     */
    protected function transition(int $orderId, string $to, array $allowedFrom): Order
    {
        return DB::transaction(function () use ($orderId, $to, $allowedFrom) {
            /** @var Order $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            // Must be paid to proceed in fulfillment transitions (including cancel of paid/processing).
            if ($order->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'order' => ['Unpaid orders cannot be moved through fulfillment.'],
                ]);
            }

            if (! in_array($order->order_status, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'order' => ['Invalid status transition.'],
                ]);
            }

            // Explicitly forbid cancelling shipped/delivered (already covered by allowedFrom).
            if ($to === 'cancelled' && in_array($order->order_status, ['shipped', 'delivered'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Shipped or delivered orders cannot be cancelled.'],
                ]);
            }

            $order->forceFill(['order_status' => $to])->save();

            return $order->fresh();
        });
    }
}

