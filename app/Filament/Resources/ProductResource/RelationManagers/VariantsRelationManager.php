<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\CartItem;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Exceptions\Halt;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('sku')
                    ->maxLength(150),
                Forms\Components\TextInput::make('barcode')
                    ->maxLength(100),
                Forms\Components\Select::make('color_id')
                    ->relationship('color', 'name_en')
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('size_id')
                    ->relationship('size', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('sale_price')
                    ->numeric(),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\Repeater::make('stockLevels')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        Forms\Components\TextInput::make('quantity_on_hand')
                            ->numeric()
                            ->required()
                            ->default(0),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')->searchable(),
                Tables\Columns\TextColumn::make('barcode')->searchable(),
                Tables\Columns\TextColumn::make('color.name_en')->label('Color'),
                Tables\Columns\TextColumn::make('size.name')->label('Size'),
                Tables\Columns\TextColumn::make('sale_price'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->isPhoenixOwned()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (ProductVariant $record): bool => ! $record->isPhoenixOwned()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (ProductVariant $record): bool => ! $record->isPhoenixOwned() && ! static::isVariantReferenced($record))
                    ->before(function (ProductVariant $record): void {
                        if (static::isVariantReferenced($record)) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot delete variant')
                                ->body('This variant is referenced in cart or order items.')
                                ->send();

                            throw new Halt();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    protected static function isVariantReferenced(ProductVariant $record): bool
    {
        return CartItem::query()->where('product_variant_id', $record->id)->exists()
            || OrderItem::query()->where('product_variant_id', $record->id)->exists();
    }
}
