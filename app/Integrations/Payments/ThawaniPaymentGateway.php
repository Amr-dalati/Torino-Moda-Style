<?php

namespace App\Integrations\Payments;

use App\Integrations\Payments\Contracts\PaymentGatewayInterface;
use App\Integrations\Payments\Exceptions\ThawaniApiException;
use App\Integrations\Payments\Exceptions\ThawaniConfigurationException;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thawani Pay hosted checkout (session-based) adapter.
 *
 * API reference: https://docs.thawani.om — Create Session, webhooks.
 * Amounts are sent in OMR baisa (1 OMR = 1000 baisa).
 *
 * Webhook assumption: JSON body with optional top-level event_id/event_type and a
 * data object containing session_id, client_reference_id, and payment_status
 * (paid | unpaid | cancelled). Signature in thawani-signature header (HMAC-SHA256
 * of raw body using THAWANI_WEBHOOK_SECRET).
 */
class ThawaniPaymentGateway implements PaymentGatewayInterface
{
    public function providerName(): string
    {
        return 'thawani';
    }

    public function createCheckout(Payment $payment): array
    {
        $config = $this->config();

        $payment->loadMissing('order.items');

        /** @var \App\Models\Order $order */
        $order = $payment->order;

        $products = $this->buildProducts($order, $payment);
        $this->assertProductsMatchPaymentAmount($products, $payment);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'thawani-api-key' => $config['secret_key'],
        ])->post(rtrim($config['base_url'], '/').'/checkout/session', [
            'client_reference_id' => $payment->merchant_reference,
            'mode' => 'payment',
            'products' => $products,
            'success_url' => $config['success_url'],
            'cancel_url' => $config['cancel_url'],
            'metadata' => [
                'merchant_reference' => $payment->merchant_reference,
                'order_id' => (string) $order->id,
                'order_number' => $order->order_number,
            ],
        ]);

        if ($response->failed()) {
            $description = (string) ($response->json('description') ?? $response->json('message') ?? 'Request failed');

            throw ThawaniApiException::fromResponse($description, $response->status());
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];

        if (($body['success'] ?? false) !== true) {
            $description = (string) ($body['description'] ?? $body['message'] ?? 'Session creation failed');

            throw ThawaniApiException::fromResponse($description);
        }

        /** @var array<string, mixed> $data */
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $sessionId = (string) ($data['session_id'] ?? '');

        if ($sessionId === '') {
            throw ThawaniApiException::fromResponse('Missing session_id in Thawani response.');
        }

        $checkoutUrl = rtrim($config['checkout_base_url'], '/')
            .'/pay/'.urlencode($sessionId)
            .'?key='.urlencode($config['publishable_key']);

        $expiresAt = now()->addMinutes((int) $config['expiry_minutes']);

        if (! empty($data['expires_at'])) {
            try {
                $expiresAt = Carbon::parse((string) $data['expires_at']);
            } catch (\Throwable) {
                // Keep configured fallback expiry.
            }
        }

        return [
            'checkout_url' => $checkoutUrl,
            'gateway_payment_id' => $sessionId,
            'expires_at' => $expiresAt,
            'raw_payload' => [
                'provider' => 'thawani',
                'session_id' => $sessionId,
                'merchant_reference' => $payment->merchant_reference,
                'response' => $data,
            ],
        ];
    }

    /**
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    public function verifyWebhookSignature(array $headers, string $payload): bool
    {
        $secret = (string) ($this->config()['webhook_secret'] ?? '');

        if ($secret === '') {
            return false;
        }

        $provided = $this->headerValue($headers, 'thawani-signature');

        if ($provided === null || $provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    public function parseWebhookEvent(array $headers, string $payload): PaymentWebhookEvent
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

        /** @var array<string, mixed> $eventData */
        $eventData = is_array($data['data'] ?? null) ? $data['data'] : $data;

        $merchantReference = $this->extractMerchantReference($data, $eventData);
        $rawStatus = (string) ($eventData['payment_status'] ?? $eventData['status'] ?? $data['payment_status'] ?? 'unknown');
        $status = $this->normalizeStatus($rawStatus);

        $eventId = isset($data['event_id'])
            ? (string) $data['event_id']
            : (isset($data['id']) ? (string) $data['id'] : null);

        if ($eventId === null || $eventId === '') {
            $sessionId = (string) ($eventData['session_id'] ?? '');
            $eventId = $sessionId !== '' ? $sessionId.':'.$status : null;
        }

        $eventType = (string) ($data['event_type'] ?? $data['type'] ?? $this->eventTypeForStatus($status));

        $occurredAt = null;
        $occurredRaw = $eventData['created_at'] ?? $eventData['updated_at'] ?? $data['created_at'] ?? null;
        if ($occurredRaw !== null && $occurredRaw !== '') {
            try {
                $occurredAt = Carbon::parse((string) $occurredRaw);
            } catch (\Throwable) {
                $occurredAt = null;
            }
        }

        return new PaymentWebhookEvent(
            provider: 'thawani',
            eventId: $eventId,
            eventType: $eventType,
            merchantReference: $merchantReference,
            status: $status,
            rawPayload: $data,
            occurredAt: $occurredAt,
        );
    }

    public function signPayload(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) $this->config()['webhook_secret']);
    }

    /**
     * @return array{
     *     secret_key: string,
     *     publishable_key: string,
     *     webhook_secret: string,
     *     base_url: string,
     *     checkout_base_url: string,
     *     success_url: string,
     *     cancel_url: string,
     *     expiry_minutes: int
     * }
     */
    protected function config(): array
    {
        /** @var array<string, mixed> $thawani */
        $thawani = config('payments.thawani', []);

        $required = [
            'secret_key' => 'THAWANI_SECRET_KEY',
            'publishable_key' => 'THAWANI_PUBLISHABLE_KEY',
            'webhook_secret' => 'THAWANI_WEBHOOK_SECRET',
            'base_url' => 'THAWANI_BASE_URL',
            'checkout_base_url' => 'THAWANI_CHECKOUT_BASE_URL',
            'success_url' => 'THAWANI_SUCCESS_URL',
            'cancel_url' => 'THAWANI_CANCEL_URL',
        ];

        $resolved = [];
        foreach ($required as $key => $envName) {
            $value = trim((string) ($thawani[$key] ?? ''));
            if ($value === '') {
                throw ThawaniConfigurationException::missing($envName);
            }
            $resolved[$key] = $value;
        }

        $resolved['expiry_minutes'] = max(1, (int) ($thawani['expiry_minutes'] ?? 30));

        return $resolved;
    }

    /**
     * @return list<array{name: string, quantity: int, unit_amount: int}>
     */
    protected function buildProducts(\App\Models\Order $order, Payment $payment): array
    {
        if ($this->amountInBaisa((string) $order->discount_total) > 0) {
            return $this->singleProductLine($payment);
        }

        if ($order->items->isEmpty()) {
            return $this->singleProductLine($payment);
        }

        $products = [];

        foreach ($order->items as $item) {
            $products[] = $this->productLineForOrderItem($item);
        }

        $deliveryBaisa = $this->amountInBaisa((string) $order->delivery_fee);
        if ($deliveryBaisa > 0) {
            $products[] = [
                'name' => 'Delivery fee',
                'quantity' => 1,
                'unit_amount' => $deliveryBaisa,
            ];
        }

        return $products;
    }

    /**
     * @return array{name: string, quantity: int, unit_amount: int}
     */
    protected function productLineForOrderItem(\App\Models\OrderItem $item): array
    {
        $lineBaisa = $this->amountInBaisa((string) $item->line_total);
        $quantity = max(1, (int) $item->quantity);

        $name = Str::limit(
            (string) ($item->product_name_en ?? $item->variant_sku ?? 'Item'),
            120,
            '',
        );

        if ($quantity > 1 && $lineBaisa % $quantity === 0) {
            return [
                'name' => $name,
                'quantity' => $quantity,
                'unit_amount' => (int) ($lineBaisa / $quantity),
            ];
        }

        return [
            'name' => $name,
            'quantity' => 1,
            'unit_amount' => max(1, $lineBaisa),
        ];
    }

    /**
     * @return list<array{name: string, quantity: int, unit_amount: int}>
     */
    protected function singleProductLine(Payment $payment): array
    {
        /** @var \App\Models\Order $order */
        $order = $payment->order;

        return [[
            'name' => 'Order '.($order->order_number ?? $payment->merchant_reference),
            'quantity' => 1,
            'unit_amount' => max(1, $this->amountInBaisa((string) $payment->amount)),
        ]];
    }

    /**
     * @param  list<array{name: string, quantity: int, unit_amount: int}>  $products
     */
    protected function assertProductsMatchPaymentAmount(array $products, Payment $payment): void
    {
        $expectedBaisa = $this->amountInBaisa((string) $payment->amount);
        $productSumBaisa = $this->sumProductsBaisa($products);

        if ($productSumBaisa !== $expectedBaisa) {
            throw ThawaniApiException::fromResponse(
                'Thawani product total does not match payment amount.',
            );
        }
    }

    /**
     * @param  list<array{name: string, quantity: int, unit_amount: int}>  $products
     */
    protected function sumProductsBaisa(array $products): int
    {
        $sum = 0;
        foreach ($products as $product) {
            $sum += (int) $product['quantity'] * (int) $product['unit_amount'];
        }

        return $sum;
    }

    protected function amountInBaisa(string $amount): int
    {
        return (int) round((float) $amount * 1000);
    }

    /**
     * @param  array<string, mixed>  $root
     * @param  array<string, mixed>  $eventData
     */
    protected function extractMerchantReference(array $root, array $eventData): ?string
    {
        $candidates = [
            $eventData['client_reference_id'] ?? null,
            is_array($eventData['metadata'] ?? null) ? ($eventData['metadata']['merchant_reference'] ?? null) : null,
            is_array($root['metadata'] ?? null) ? ($root['metadata']['merchant_reference'] ?? null) : null,
            $root['client_reference_id'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    protected function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'paid', 'success', 'succeeded', 'successful' => 'paid',
            'unpaid', 'failed', 'failure', 'declined' => 'failed',
            'cancelled', 'canceled' => 'cancelled',
            'expired', 'timeout', 'timed_out' => 'expired',
            default => $status,
        };
    }

    protected function eventTypeForStatus(string $status): string
    {
        return match ($status) {
            'paid' => 'payment.succeeded',
            'expired' => 'payment.expired',
            'cancelled' => 'payment.cancelled',
            default => 'payment.failed',
        };
    }

    /**
     * @param  array<string, string|array<int, string>|null>  $headers
     */
    protected function headerValue(array $headers, string $name): ?string
    {
        $lower = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) !== $lower) {
                continue;
            }

            if (is_array($value)) {
                return $value[0] ?? null;
            }

            return $value;
        }

        return null;
    }
}
