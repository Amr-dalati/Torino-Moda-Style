<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'quantity' => $this->quantity,
            'unit_price_snapshot' => $this->unit_price_snapshot,
            'line_total' => $this->line_total,
            'product_code' => $this->product_code,
            'variant_sku' => $this->variant_sku,
            'variant_barcode' => $this->variant_barcode,
            'product_name_en' => $this->product_name_en,
            'product_name_ar' => $this->product_name_ar,
            'color_code' => $this->color_code,
            'size_code' => $this->size_code,
        ];
    }
}

