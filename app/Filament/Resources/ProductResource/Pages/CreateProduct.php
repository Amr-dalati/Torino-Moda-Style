<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Enums\ProductSource;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['source'] = ProductSource::Manual->value;

        return $data;
    }
}
