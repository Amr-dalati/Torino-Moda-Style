<?php

namespace App\Filament\Resources\BrandResource\Pages;

use App\Filament\Resources\BrandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBrand extends EditRecord
{
    protected static string $resource = BrandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => ! $this->getRecord()->isPhoenixOwned()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['logo_path'])) {
            $data['logo_disk'] = 'public';
        }

        $data['name'] = filled($data['name'] ?? null)
            ? $data['name']
            : ($data['name_en'] ?? $data['name_ar'] ?? $data['code'] ?? '');

        return $data;
    }
}
