<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CustomerAddress */
class CustomerAddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'area_name' => $this->area_name,
            'postal_code' => $this->postal_code,
            'is_default' => $this->is_default,
            'delivery_area' => $this->whenLoaded('deliveryArea', fn () => [
                'id' => $this->deliveryArea?->id,
                'code' => $this->deliveryArea?->code,
                'name_ar' => $this->deliveryArea?->name_ar,
                'name_en' => $this->deliveryArea?->name_en,
                'delivery_fee' => $this->deliveryArea?->delivery_fee,
                'region' => $this->deliveryArea?->relationLoaded('region') ? [
                    'id' => $this->deliveryArea->region?->id,
                    'code' => $this->deliveryArea->region?->code,
                    'name_ar' => $this->deliveryArea->region?->name_ar,
                    'name_en' => $this->deliveryArea->region?->name_en,
                ] : null,
            ]),
        ];
    }
}

