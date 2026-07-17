<?php

namespace App\Filament\Resources;

use App\Enums\ProductSource;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        $phoenixLocked = fn (?Product $record): bool => $record?->isPhoenixOwned() ?? false;

        return $form
            ->schema([
                Forms\Components\TextInput::make('product_code')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->disabled($phoenixLocked),
                Forms\Components\TextInput::make('barcode')
                    ->maxLength(100)
                    ->disabled($phoenixLocked),
                Forms\Components\TextInput::make('name_en')
                    ->maxLength(255)
                    ->disabled($phoenixLocked),
                Forms\Components\TextInput::make('name_ar')
                    ->maxLength(255)
                    ->disabled($phoenixLocked),
                Forms\Components\Textarea::make('description_en')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('description_ar')
                    ->columnSpanFull(),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name_en')
                    ->searchable()
                    ->preload()
                    ->disabled($phoenixLocked),
                Forms\Components\Select::make('brand_id')
                    ->relationship('brand', 'name_en')
                    ->searchable()
                    ->preload()
                    ->disabled($phoenixLocked),
                Forms\Components\TextInput::make('sale_price')
                    ->numeric()
                    ->disabled($phoenixLocked),
                Forms\Components\Select::make('source')
                    ->options(ProductSource::class)
                    ->disabled()
                    ->dehydrated(false)
                    ->visible($phoenixLocked),
                Forms\Components\Toggle::make('is_active')
                    ->required(),
                Forms\Components\Toggle::make('is_visible')
                    ->required(),
                Forms\Components\Toggle::make('is_featured')
                    ->required(),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('primary_image')
                    ->label('Image')
                    ->getStateUsing(fn (Product $record): ?string => $record->primaryImage?->path)
                    ->disk('public')
                    ->visibility('public'),
                Tables\Columns\TextColumn::make('product_code')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name_en')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category.name_en')->label('Category')->sortable(),
                Tables\Columns\TextColumn::make('brand.name_en')->label('Brand')->sortable(),
                Tables\Columns\TextColumn::make('source')->badge()->sortable(),
                Tables\Columns\IconColumn::make('is_visible')->boolean()->sortable(),
                Tables\Columns\IconColumn::make('is_featured')->boolean()->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->options(ProductSource::class),
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name_en')
                    ->label('Category'),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->relationship('brand', 'name_en')
                    ->label('Brand'),
                Tables\Filters\TernaryFilter::make('is_visible'),
                Tables\Filters\TernaryFilter::make('is_featured'),
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
            ->with(['category', 'brand', 'primaryImage']);
    }

    public static function canDelete($record): bool
    {
        return ! $record->isPhoenixOwned();
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ImagesRelationManager::class,
            RelationManagers\VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
