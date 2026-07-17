<?php

namespace App\Observers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Support\CatalogCache;

class CatalogCacheObserver
{
    public function saved(
        Product|Category|Brand|ProductImage|ProductVariant|StockLevel $model,
    ): void {
        CatalogCache::flush();
    }

    public function deleted(
        Product|Category|Brand|ProductImage|ProductVariant|StockLevel $model,
    ): void {
        CatalogCache::flush();
    }
}
