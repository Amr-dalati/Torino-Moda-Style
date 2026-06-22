<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Payment */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'method' => $this->method,
            'amount' => $this->amount,
            'currency' => $this->currency ?? config('app.currency'),
            'status' => $this->status,
            'merchant_reference' => $this->merchant_reference,
            'gateway_payment_id' => $this->gateway_payment_id,
            'checkout_url' => $this->checkout_url,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}

