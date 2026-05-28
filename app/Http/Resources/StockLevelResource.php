<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\StockLevel */
class StockLevelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse?->id,
                'code' => $this->warehouse?->code,
                'name' => $this->warehouse?->name,
            ]),
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant?->id,
                'barcode' => $this->variant?->barcode,
                'sku' => $this->variant?->sku,
                'product' => $this->variant?->relationLoaded('product') ? [
                    'id' => $this->variant->product?->id,
                    'product_code' => $this->variant->product?->product_code,
                    'name_en' => $this->variant->product?->name_en,
                    'name_ar' => $this->variant->product?->name_ar,
                ] : null,
                'color' => $this->variant?->relationLoaded('color') ? [
                    'code' => $this->variant->color?->code,
                    'name_en' => $this->variant->color?->name_en,
                    'name_ar' => $this->variant->color?->name_ar,
                ] : null,
                'size' => $this->variant?->relationLoaded('size') ? [
                    'code' => $this->variant->size?->code,
                    'name' => $this->variant->size?->name,
                ] : null,
            ]),
            'quantity_on_hand' => $this->quantity_on_hand,
            'quantity_reserved' => $this->quantity_reserved,
            'quantity_available' => (float) $this->quantity_on_hand - (float) $this->quantity_reserved,
            'synced_at' => $this->synced_at?->toIso8601String(),
        ];
    }
}

