<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Integrations\Payments\Exceptions\UnsupportedPaymentProviderException;
use App\Integrations\Payments\PaymentGatewayResolver;
use App\Services\Checkout\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayResolver $gateways,
        protected PaymentService $payments,
    ) {}

    public function __invoke(Request $request, string $provider): JsonResponse
    {
        $payload = $request->getContent();

        try {
            $gateway = $this->gateways->resolve($provider);
        } catch (UnsupportedPaymentProviderException) {
            return ApiResponse::error('Unsupported payment provider.', 404);
        }

        $headers = $request->headers->all();
        $signatureValid = $gateway->verifyWebhookSignature($headers, $payload);

        $parsedPayload = [];
        try {
            $parsedPayload = json_decode($payload, true) ?? [];
        } catch (Throwable) {
            $parsedPayload = ['raw' => $payload];
        }

        if (! $signatureValid) {
            $this->payments->recordWebhookAttempt(
                provider: $provider,
                eventType: 'unknown',
                gatewayEventId: null,
                signatureValid: false,
                payload: is_array($parsedPayload) ? $parsedPayload : ['raw' => $payload],
                processingStatus: 'failed',
                errorMessage: 'Invalid webhook signature.',
            );

            return ApiResponse::error('Invalid webhook signature.', 401);
        }

        try {
            $event = $gateway->parseWebhookEvent($headers, $payload);
        } catch (Throwable $e) {
            Log::warning('Payment webhook parse failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            $this->payments->recordWebhookAttempt(
                provider: $provider,
                eventType: 'parse_error',
                gatewayEventId: null,
                signatureValid: true,
                payload: is_array($parsedPayload) ? $parsedPayload : ['raw' => $payload],
                processingStatus: 'failed',
                errorMessage: 'Unable to parse webhook payload.',
            );

            return ApiResponse::error('Invalid webhook payload.', 422);
        }

        if ($this->payments->findExistingWebhook($event->provider, $event->eventId)) {
            $duplicate = $this->payments->recordWebhookAttempt(
                provider: $event->provider,
                eventType: $event->eventType,
                gatewayEventId: null,
                signatureValid: true,
                payload: array_merge($event->rawPayload ?? [], [
                    'duplicate_of_event_id' => $event->eventId,
                ]),
                processingStatus: 'ignored',
                errorMessage: 'Duplicate gateway event.',
            );

            return ApiResponse::success([
                'webhook_id' => $duplicate->id,
                'duplicate' => true,
            ], 'Webhook already received.');
        }

        $webhook = $this->payments->recordWebhookAttempt(
            provider: $event->provider,
            eventType: $event->eventType,
            gatewayEventId: $event->eventId,
            signatureValid: true,
            payload: $event->rawPayload ?? [],
            processingStatus: 'pending',
        );

        try {
            $payment = $this->payments->handleWebhookEvent($event);

            if ($payment === null) {
                $webhook->forceFill([
                    'processing_status' => 'ignored',
                    'processed_at' => now(),
                    'error_message' => 'Unknown merchant reference.',
                ])->save();

                return ApiResponse::success([
                    'webhook_id' => $webhook->id,
                    'processed' => false,
                ], 'Webhook received.');
            }

            $webhook->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            return ApiResponse::success([
                'webhook_id' => $webhook->id,
                'payment_id' => $payment->id,
                'payment_status' => $payment->status,
            ], 'Webhook processed.');
        } catch (ValidationException $e) {
            $webhook->forceFill([
                'processing_status' => 'ignored',
                'processed_at' => now(),
                'error_message' => 'Payment state conflict.',
            ])->save();

            return ApiResponse::success([
                'webhook_id' => $webhook->id,
                'processed' => false,
            ], 'Webhook ignored.');
        } catch (Throwable $e) {
            Log::error('Payment webhook processing failed', [
                'provider' => $provider,
                'event_id' => $event->eventId,
                'error' => $e->getMessage(),
            ]);

            $webhook->forceFill([
                'processing_status' => 'failed',
                'processed_at' => now(),
                'error_message' => 'Processing error.',
            ])->save();

            return ApiResponse::error('Webhook processing failed.', 500);
        }
    }
}
