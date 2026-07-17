<?php

namespace App\Filament\Resources\BrandResource\Pages;

use App\Filament\Resources\BrandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBrand extends CreateRecord
{
    protected static string $resource = BrandResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['logo_disk'] = 'public';
        $data['name'] = filled($data['name'] ?? null)
            ? $data['name']
            : ($data['name_en'] ?? $data['name_ar'] ?? $data['code'] ?? '');

        return $data;
    }
}
