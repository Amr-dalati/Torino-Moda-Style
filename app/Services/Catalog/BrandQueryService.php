<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use Illuminate\Support\Collection;

class BrandQueryService
{
    /**
     * @return Collection<int, Brand>
     */
    public function listActive(): Collection
    {
        return Brand::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->orderBy('name')
            ->get();
    }
}
