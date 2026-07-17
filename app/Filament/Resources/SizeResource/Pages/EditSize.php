<?php

namespace App\Filament\Resources\SizeResource\Pages;

use App\Filament\Resources\SizeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSize extends EditRecord
{
    protected static string $resource = SizeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn () => $this->getRecord()->phoenix_id === null),
        ];
    }
}
