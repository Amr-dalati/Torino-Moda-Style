<?php

namespace App\Services\Payments;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Resolves orders from Thawani browser return requests and builds mobile deep links.
 *
 * Never mutates payment or order state — webhooks remain authoritative.
 */
class ThawaniReturnService
{
    /**
     * Resolve an order id from safe Thawani return query parameters.
     *
     * Accepted: session_id (gateway session), client_reference_id (merchant reference).
     * Rejected: arbitrary redirect URLs, raw order_id without payment linkage.
     */
    public function resolveOrderId(Request $request): ?int
    {
        $sessionId = $this->sanitizeIdentifier($request->query('session_id'));
        if ($sessionId !== null) {
            $orderId = $this->orderIdForGatewaySession($sessionId);
            if ($orderId !== null) {
                return $orderId;
            }
        }

        $clientReference = $this->sanitizeIdentifier($request->query('client_reference_id'));
        if ($clientReference !== null) {
            $orderId = $this->orderIdForMerchantReference($clientReference);
            if ($orderId !== null) {
                return $orderId;
            }
        }

        return null;
    }

    /**
     * Build the configured mobile success deep link, optionally including order_id.
     *
     * Returns null when the configured URL is missing or not an allowed custom scheme.
     */
    public function mobileSuccessRedirectUrl(?int $orderId): ?string
    {
        return $this->buildMobileRedirectUrl(
            (string) config('payments.mobile.payment_success_url', ''),
            $orderId,
        );
    }

    /**
     * Build the configured mobile cancel deep link, optionally including order_id.
     */
    public function mobileCancelRedirectUrl(?int $orderId): ?string
    {
        return $this->buildMobileRedirectUrl(
            (string) config('payments.mobile.payment_cancel_url', ''),
            $orderId,
        );
    }

    public function orderIdForGatewaySession(string $sessionId): ?int
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('gateway_payment_id', $sessionId)
            ->first();

        return $payment ? (int) $payment->order_id : null;
    }

    public function orderIdForMerchantReference(string $merchantReference): ?int
    {
        /** @var Payment|null $payment */
        $payment = Payment::query()
            ->where('merchant_reference', $merchantReference)
            ->first();

        return $payment ? (int) $payment->order_id : null;
    }

    protected function buildMobileRedirectUrl(string $configuredUrl, ?int $orderId): ?string
    {
        $configuredUrl = trim($configuredUrl);
        if ($configuredUrl === '') {
            return null;
        }

        $parts = parse_url($configuredUrl);
        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($scheme !== 'torinomodastyle') {
            return null;
        }

        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '');
        if ($host === '' || $path === '') {
            return null;
        }

        $query = [];
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }

        if ($orderId !== null && $orderId > 0) {
            $query['order_id'] = (string) $orderId;
        }

        $built = $scheme.'://'.$host.$path;
        if ($query !== []) {
            $built .= '?'.http_build_query($query);
        }

        return $built;
    }

    protected function sanitizeIdentifier(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '' || strlen($trimmed) > 191) {
            return null;
        }

        if (! preg_match('/^[A-Za-z0-9._:-]+$/', $trimmed)) {
            return null;
        }

        return $trimmed;
    }
}
