<?php

namespace App\Services\Admin;

use App\Models\Order;
use App\Services\Stock\StockReservationService;
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

    /**
     * Paid-order cancellation is blocked until a refund workflow exists.
     * Stock committed at payment is not restocked by this path.
     */
    public function cancel(int $orderId): Order
    {
        throw ValidationException::withMessages([
            'order' => ['Paid orders cannot be cancelled without a refund workflow.'],
        ]);
    }

    /**
     * Cancel unpaid or failed-payment orders and release active reservations once.
     */
    public function cancelUnpaid(int $orderId): Order
    {
        return DB::transaction(function () use ($orderId) {
            /** @var Order $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

            if ($order->payment_status === 'paid') {
                throw ValidationException::withMessages([
                    'order' => ['Paid orders cannot be cancelled without a refund workflow.'],
                ]);
            }

            if (in_array($order->order_status, ['shipped', 'delivered', 'cancelled'], true)) {
                throw ValidationException::withMessages([
                    'order' => ['Order cannot be cancelled in its current state.'],
                ]);
            }

            if ($order->stock_committed_at !== null) {
                throw ValidationException::withMessages([
                    'order' => ['Stock already committed; cannot cancel this order.'],
                ]);
            }

            app(StockReservationService::class)->releaseForOrder($order);

            $paymentStatus = $order->payment_status;
            if ($paymentStatus === 'pending') {
                $paymentStatus = 'cancelled';
            }

            $order->forceFill([
                'order_status' => 'cancelled',
                'payment_status' => $paymentStatus,
            ])->save();

            return $order->fresh();
        });
    }

    /**
     * @param  list<string>  $allowedFrom
     */
    protected function transition(int $orderId, string $to, array $allowedFrom): Order
    {
        return DB::transaction(function () use ($orderId, $to, $allowedFrom) {
            /** @var Order $order */
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();

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
