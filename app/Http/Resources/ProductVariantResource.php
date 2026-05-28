<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ProductVariant */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phoenix_id' => $this->phoenix_id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'color' => $this->whenLoaded('color', fn () => [
                'id' => $this->color?->id,
                'code' => $this->color?->code,
                'name_ar' => $this->color?->name_ar,
                'name_en' => $this->color?->name_en,
            ]),
            'size' => $this->whenLoaded('size', fn () => [
                'id' => $this->size?->id,
                'code' => $this->size?->code,
                'name' => $this->size?->name,
            ]),
            'sale_price' => $this->sale_price,
            'is_active' => $this->is_active,
        ];
    }
}

