<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryAreaResource\Pages;
use App\Models\DeliveryArea;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeliveryAreaResource extends Resource
{
    protected static ?string $model = DeliveryArea::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('delivery_region_id')
                    ->relationship('region', 'code')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('code')
                    ->required()
                    ->maxLength(50)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name_en')
                    ->maxLength(190),
                Forms\Components\TextInput::make('name_ar')
                    ->maxLength(190),
                Forms\Components\TextInput::make('delivery_fee')
                    ->numeric()
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('region.code')->label('Region')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('name_en')->searchable(),
                Tables\Columns\TextColumn::make('delivery_fee')->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('delivery_region_id')
                    ->relationship('region', 'code')
                    ->label('Region'),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['region']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeliveryAreas::route('/'),
            'create' => Pages\CreateDeliveryArea::route('/create'),
            'view' => Pages\ViewDeliveryArea::route('/{record}'),
            'edit' => Pages\EditDeliveryArea::route('/{record}/edit'),
        ];
    }
}

