<?php

namespace App\Services\Checkout;

use App\Integrations\Payments\Contracts\PaymentGatewayInterface;
use App\Integrations\Payments\PaymentWebhookEvent;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Services\Stock\StockReservationService;
use App\Support\SensitiveDataRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected StockReservationService $stock,
    ) {}

    public function start(Payment $payment): Payment
    {
        $result = $this->gateway->createCheckout($payment);

        $payment->forceFill([
            'checkout_url' => $result['checkout_url'],
            'gateway_payment_id' => $result['gateway_payment_id'],
            'expires_at' => $result['expires_at'],
            'raw_payload' => isset($result['raw_payload']) ? SensitiveDataRedactor::redact($result['raw_payload']) : null,
        ])->save();

        return $payment->fresh();
    }

    public function markMockSuccess(string $merchantReference, array $payload = []): Order
    {
        return DB::transaction(function () use ($merchantReference, $payload) {
            /** @var Payment $payment */
            $payment = Payment::query()->where('merchant_reference', $merchantReference)->lockForUpdate()->firstOrFail();

            return $this->markPaid($payment, $payload);
        });
    }

    /**
     * Process a normalized webhook event. Returns null when merchant_reference is unknown.
     */
    public function handleWebhookEvent(PaymentWebhookEvent $event): ?Payment
    {
        if ($event->merchantReference === null || $event->merchantReference === '') {
            return null;
        }

        if ($event->isPaid()) {
            return DB::transaction(function () use ($event) {
                /** @var Payment|null $payment */
                $payment = Payment::query()
                    ->where('merchant_reference', $event->merchantReference)
                    ->lockForUpdate()
                    ->first();

                if (! $payment) {
                    return null;
                }

                $this->markPaid($payment, $event->rawPayload ?? []);

                return $payment->fresh();
            });
        }

        if ($event->isExpired()) {
            /** @var Payment|null $payment */
            $payment = Payment::query()->where('merchant_reference', $event->merchantReference)->first();
            if (! $payment) {
                return null;
            }

            $this->markPaymentExpired($event->merchantReference);

            return $payment->fresh();
        }

        if ($event->isFailed() || $event->isCancelled()) {
            /** @var Payment|null $payment */
            $payment = Payment::query()->where('merchant_reference', $event->merchantReference)->first();
            if (! $payment) {
                return null;
            }

            $message = $event->isCancelled() ? 'Payment cancelled.' : null;
            $this->markPaymentFailed($event->merchantReference, $message);

            return $payment->fresh();
        }

        return null;
    }

    /**
     * Mark payment failed and release stock reservation (awaiting_payment orders only).
     * Idempotent when payment is already failed.
     */
    public function markPaymentFailed(string $merchantReference, ?string $failureMessage = null): Order
    {
        return $this->finalizeUnpaidPayment(
            $merchantReference,
            paymentStatus: 'failed',
            orderPaymentStatus: 'failed',
            orderStatus: 'payment_failed',
            failureMessage: $failureMessage,
        );
    }

    /**
     * Mark payment expired and release stock reservation (awaiting_payment orders only).
     * Idempotent when payment is already expired.
     */
    public function markPaymentExpired(string $merchantReference): Order
    {
        return $this->finalizeUnpaidPayment(
            $merchantReference,
            paymentStatus: 'expired',
            orderPaymentStatus: 'expired',
            orderStatus: 'payment_failed',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function markPaid(Payment $payment, array $payload = []): Order
    {
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
            'raw_payload' => $payload !== [] ? SensitiveDataRedactor::redact($payload) : $payment->raw_payload,
        ])->save();

        /** @var Order $order */
        $order = $payment->order()->lockForUpdate()->firstOrFail();
        $order->forceFill([
            'payment_status' => 'paid',
            'order_status' => 'paid',
        ])->save();

        $this->stock->commitForPaidOrder($order);

        if ($order->cart_id) {
            Cart::query()
                ->whereKey($order->cart_id)
                ->where('status', 'active')
                ->update(['status' => 'checked_out']);
        }

        return $order->fresh()->load(['items', 'payments']);
    }

    /**
     * @param  'failed'|'expired'  $paymentStatus
     */
    protected function finalizeUnpaidPayment(
        string $merchantReference,
        string $paymentStatus,
        string $orderPaymentStatus,
        string $orderStatus,
        ?string $failureMessage = null,
    ): Order {
        return DB::transaction(function () use ($merchantReference, $paymentStatus, $orderPaymentStatus, $orderStatus, $failureMessage) {
            /** @var Payment $payment */
            $payment = Payment::query()->where('merchant_reference', $merchantReference)->lockForUpdate()->firstOrFail();

            if ($payment->status === $paymentStatus) {
                return $payment->order()->with(['items', 'payments'])->firstOrFail();
            }

            if ($payment->status === 'paid') {
                throw ValidationException::withMessages([
                    'payment' => ['Paid payments cannot be marked as failed or expired.'],
                ]);
            }

            if ($payment->status !== 'pending') {
                throw ValidationException::withMessages([
                    'payment' => ['Payment is not in a releasable state.'],
                ]);
            }

            $payment->forceFill([
                'status' => $paymentStatus,
                'failed_at' => now(),
                'failure_message' => $failureMessage,
            ])->save();

            /** @var Order $order */
            $order = $payment->order()->lockForUpdate()->firstOrFail();
            $order->forceFill([
                'payment_status' => $orderPaymentStatus,
                'order_status' => $orderStatus,
            ])->save();

            $this->stock->releaseForOrder($order);

            return $order->fresh()->load(['items', 'payments']);
        });
    }

    public function recordWebhookAttempt(
        string $provider,
        string $eventType,
        ?string $gatewayEventId,
        bool $signatureValid,
        array $payload,
        string $processingStatus,
        ?string $errorMessage = null,
    ): PaymentWebhook {
        return PaymentWebhook::query()->create([
            'provider' => $provider,
            'event_type' => $eventType,
            'gateway_event_id' => $gatewayEventId,
            'signature_valid' => $signatureValid,
            'payload' => SensitiveDataRedactor::redact($payload),
            'received_at' => now(),
            'processed_at' => in_array($processingStatus, ['processed', 'ignored', 'failed'], true) ? now() : null,
            'processing_status' => $processingStatus,
            'error_message' => $errorMessage,
        ]);
    }

    public function findExistingWebhook(string $provider, ?string $gatewayEventId): ?PaymentWebhook
    {
        if ($gatewayEventId === null || $gatewayEventId === '') {
            return null;
        }

        return PaymentWebhook::query()
            ->where('provider', $provider)
            ->where('gateway_event_id', $gatewayEventId)
            ->first();
    }
}
