<?php

namespace App\Services\Checkout;

use App\Integrations\Payments\Contracts\PaymentGatewayInterface;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
    ) {}

    public function start(Payment $payment): Payment
    {
        $result = $this->gateway->createCheckout($payment);

        $payment->forceFill([
            'checkout_url' => $result['checkout_url'],
            'gateway_payment_id' => $result['gateway_payment_id'],
            'expires_at' => $result['expires_at'],
            'raw_payload' => $result['raw_payload'] ?? null,
        ])->save();

        return $payment->fresh();
    }

    public function markMockSuccess(string $merchantReference, array $payload = []): Order
    {
        return DB::transaction(function () use ($merchantReference, $payload) {
            /** @var Payment $payment */
            $payment = Payment::query()->where('merchant_reference', $merchantReference)->lockForUpdate()->firstOrFail();

            // Idempotent success: if already paid, return current order state without duplicating changes.
            if ($payment->status === 'paid') {
                return $payment->order()->with(['items', 'payments'])->firstOrFail();
            }

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => ['Payment is not in a payable state.'],
                ]);
            }

            $payment->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
                'raw_payload' => $payload ?: $payment->raw_payload,
            ])->save();

            /** @var Order $order */
            $order = $payment->order()->lockForUpdate()->firstOrFail();
            $order->forceFill([
                'payment_status' => 'paid',
                'order_status' => 'paid',
            ])->save();

            if ($order->cart_id) {
                Cart::query()
                    ->whereKey($order->cart_id)
                    ->where('status', 'active')
                    ->update(['status' => 'checked_out']);
            }

            PaymentWebhook::query()->create([
                'provider' => 'mock',
                'event_type' => 'payment_succeeded',
                'gateway_event_id' => null,
                'signature_valid' => true,
                'payload' => [
                    'merchant_reference' => $merchantReference,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                ],
                'received_at' => now(),
                'processed_at' => now(),
                'processing_status' => 'processed',
                'error_message' => null,
            ]);

            return $order->fresh()->load(['items', 'payments']);
        });
    }
}

