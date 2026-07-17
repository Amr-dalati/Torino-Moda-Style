<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Collection;

class CategoryQueryService
{
    /**
     * @return Collection<int, Category>
     */
    public function listActive(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name_en')
            ->orderBy('name_ar')
            ->get();
    }
}
