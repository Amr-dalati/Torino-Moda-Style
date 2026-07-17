<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Models\StockAdjustment;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Adjustments';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('stockLevel.id')->label('Stock level'),
            Infolists\Components\TextEntry::make('variant.product.name_en')->label('Product'),
            Infolists\Components\TextEntry::make('warehouse.name')->label('Warehouse'),
            Infolists\Components\TextEntry::make('user.name')->label('Adjusted by'),
            Infolists\Components\TextEntry::make('adjustment_type')->badge(),
            Infolists\Components\TextEntry::make('quantity_before')->numeric(decimalPlaces: 3),
            Infolists\Components\TextEntry::make('quantity_change')->numeric(decimalPlaces: 3),
            Infolists\Components\TextEntry::make('quantity_after')->numeric(decimalPlaces: 3),
            Infolists\Components\TextEntry::make('reason'),
            Infolists\Components\TextEntry::make('reference'),
            Infolists\Components\TextEntry::make('created_at')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('variant.product.name_en')->label('Product')->searchable(),
                Tables\Columns\TextColumn::make('warehouse.name')->label('Warehouse')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('User')->sortable(),
                Tables\Columns\TextColumn::make('adjustment_type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('quantity_before')->numeric(decimalPlaces: 0),
                Tables\Columns\TextColumn::make('quantity_change')->numeric(decimalPlaces: 0),
                Tables\Columns\TextColumn::make('quantity_after')->numeric(decimalPlaces: 0),
                Tables\Columns\TextColumn::make('reason')->limit(40),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['variant.product', 'warehouse', 'user']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user() instanceof User && auth()->user()->isAdmin();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'view' => Pages\ViewStockAdjustment::route('/{record}'),
        ];
    }
}
