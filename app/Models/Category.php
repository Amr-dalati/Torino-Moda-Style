<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = [
        'code',
        'name_ar',
        'name_en',
        'image_path',
        'image_disk',
        'phoenix_id',
        'is_active',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public function isPhoenixOwned(): bool
    {
        return $this->phoenix_id !== null;
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function imageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk($this->image_disk ?: 'public')->url($this->image_path);
    }
}
