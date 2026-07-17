<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Brand extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'name_en',
        'logo_path',
        'logo_disk',
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

    public function displayNameEn(): ?string
    {
        return $this->name_en ?: $this->name;
    }

    /**
     * Safe non-null display name for API consumers (e.g. Flutter requires `name`).
     */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return (string) $this->name;
        }

        if (filled($this->name_en)) {
            return (string) $this->name_en;
        }

        if (filled($this->name_ar)) {
            return (string) $this->name_ar;
        }

        if (filled($this->code)) {
            return (string) $this->code;
        }

        return '';
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk($this->logo_disk ?: 'public')->url($this->logo_path);
    }
}
