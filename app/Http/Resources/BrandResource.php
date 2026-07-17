<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Brand */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->displayName(),
            'name_ar' => $this->name_ar,
            'name_en' => $this->displayNameEn(),
            'logo_url' => $this->logoUrl(),
            'is_active' => $this->is_active,
        ];
    }
}
