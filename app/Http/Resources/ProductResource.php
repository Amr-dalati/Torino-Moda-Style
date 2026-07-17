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
        $primary = $this->relationLoaded('primaryImage')
            ? $this->primaryImage
            : ($this->relationLoaded('images') ? $this->images->firstWhere('is_primary', true) : null);

        return [
            'id' => $this->id,
            'phoenix_id' => $this->phoenix_id,
            'product_code' => $this->product_code,
            'barcode' => $this->barcode,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'code' => $this->category?->code,
                'name_ar' => $this->category?->name_ar,
                'name_en' => $this->category?->name_en,
                'image_url' => $this->category?->imageUrl(),
            ]),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand?->id,
                'code' => $this->brand?->code,
                'name' => $this->brand?->displayName(),
                'name_ar' => $this->brand?->name_ar,
                'name_en' => $this->brand?->displayNameEn(),
                'logo_url' => $this->brand?->logoUrl(),
            ]),
            'sale_price' => $this->sale_price,
            'is_active' => $this->is_active,
            'is_visible' => $this->is_visible,
            'is_featured' => $this->is_featured,
            'primary_image_url' => $primary?->url(),
            'images' => $this->relationLoaded('images')
                ? ProductImageResource::collection($this->images)->resolve()
                : [],
            'variants_count' => $this->when(isset($this->variants_count), $this->variants_count),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
