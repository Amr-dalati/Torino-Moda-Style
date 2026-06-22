<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_status' => $this->order_status,
            'payment_status' => $this->payment_status,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'currency' => $this->currency ?? config('app.currency'),
            'shipping' => [
                'label' => $this->shipping_label,
                'recipient_name' => $this->shipping_recipient_name,
                'recipient_phone' => $this->shipping_recipient_phone,
                'address_line1' => $this->shipping_address_line1,
                'address_line2' => $this->shipping_address_line2,
                'city' => $this->shipping_city,
                'area_name' => $this->shipping_area_name,
                'postal_code' => $this->shipping_postal_code,
                'delivery_region_code' => $this->shipping_delivery_region_code,
                'delivery_area_code' => $this->shipping_delivery_area_code,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

