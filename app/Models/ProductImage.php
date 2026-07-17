<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'disk',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (ProductImage $image) {
            if ($image->is_primary) {
                static::query()
                    ->where('product_id', $image->product_id)
                    ->whereKeyNot($image->id)
                    ->update(['is_primary' => false]);
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): ?string
    {
        if ($this->path === '') {
            return null;
        }

        return Storage::disk($this->disk)->url($this->path);
    }
}
