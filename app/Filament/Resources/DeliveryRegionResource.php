<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryRegionResource\Pages;
use App\Models\DeliveryRegion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliveryRegionResource extends Resource
{
    protected static ?string $model = DeliveryRegion::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name_en')
                    ->maxLength(190),
                Forms\Components\TextInput::make('name_ar')
                    ->maxLength(190),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_en')->searchable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryRegions::route('/'),
            'create' => Pages\CreateDeliveryRegion::route('/create'),
            'view' => Pages\ViewDeliveryRegion::route('/{record}'),
            'edit' => Pages\EditDeliveryRegion::route('/{record}/edit'),
        ];
    }
}

