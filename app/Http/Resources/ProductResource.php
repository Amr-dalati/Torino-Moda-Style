<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phoenix_id' => $this->phoenix_id,
            'product_code' => $this->product_code,
            'barcode' => $this->barcode,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'code' => $this->category?->code,
                'name_ar' => $this->category?->name_ar,
                'name_en' => $this->category?->name_en,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand?->id,
                'code' => $this->brand?->code,
                'name' => $this->brand?->name,
            ]),
            'sale_price' => $this->sale_price,
            'is_active' => $this->is_active,
            'variants_count' => $this->when(isset($this->variants_count), $this->variants_count),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}

