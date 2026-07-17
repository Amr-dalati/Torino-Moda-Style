<?php

namespace App\Support\Catalog;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProductImageOptimizer
{
    public const MAX_WIDTH = 1600;

    public const WEBP_QUALITY = 80;

    /**
     * Convert uploaded product image to optimized WebP on the public disk.
     * Existing WebP/JPEG/PNG paths that cannot be processed are left unchanged.
     */
    public function optimize(ProductImage $image): void
    {
        if ($image->path === '' || $image->disk !== 'public') {
            return;
        }

        if (! extension_loaded('gd')) {
            return;
        }

        $disk = Storage::disk($image->disk);

        if (! $disk->exists($image->path)) {
            return;
        }

        $oldPath = $image->path;

        try {
            $manager = new ImageManager(new Driver);
            $processed = $manager->read($disk->path($oldPath));

            if ($processed->width() > self::MAX_WIDTH) {
                $processed->scaleDown(width: self::MAX_WIDTH);
            }

            $newPath = dirname($oldPath).'/'.Str::uuid()->toString().'.webp';
            $disk->put($newPath, (string) $processed->toWebp(quality: self::WEBP_QUALITY));

            $image->path = $newPath;
            $image->save();

            if ($oldPath !== $newPath && $disk->exists($oldPath)) {
                $disk->delete($oldPath);
            }
        } catch (\Throwable $e) {
            Log::warning('Product image optimization skipped', [
                'product_image_id' => $image->id,
                'path' => $oldPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
