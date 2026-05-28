<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CartItem */
class CartItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'unit_price_snapshot' => $this->unit_price_snapshot,
            'line_total' => $this->line_total,
            'variant' => $this->whenLoaded('variant', fn () => [
                'id' => $this->variant?->id,
                'sku' => $this->variant?->sku,
                'barcode' => $this->variant?->barcode,
                'sale_price' => $this->variant?->sale_price,
                'color' => $this->variant?->relationLoaded('color') ? [
                    'code' => $this->variant->color?->code,
                    'name_en' => $this->variant->color?->name_en,
                    'name_ar' => $this->variant->color?->name_ar,
                ] : null,
                'size' => $this->variant?->relationLoaded('size') ? [
                    'code' => $this->variant->size?->code,
                    'name' => $this->variant->size?->name,
                ] : null,
                'product' => $this->variant?->relationLoaded('product') ? [
                    'id' => $this->variant->product?->id,
                    'product_code' => $this->variant->product?->product_code,
                    'name_en' => $this->variant->product?->name_en,
                    'name_ar' => $this->variant->product?->name_ar,
                    'sale_price' => $this->variant->product?->sale_price,
                ] : null,
            ]),
        ];
    }
}

