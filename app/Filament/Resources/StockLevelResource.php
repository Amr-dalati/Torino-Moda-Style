<?php

namespace App\Filament\Resources;

use App\Enums\ProductSource;
use App\Enums\StockAdjustmentType;
use App\Filament\Resources\StockLevelResource\Pages;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\Stock\StockAdjustmentService;
use App\Support\InventoryStatus;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockLevelResource extends Resource
{
    protected static ?string $model = StockLevel::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Stock Levels';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('variant.product.name_en')
                    ->label('Product')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('variant.product', function (Builder $productQuery) use ($search) {
                            $productQuery
                                ->where('name_en', 'like', "%{$search}%")
                                ->orWhere('name_ar', 'like', "%{$search}%")
                                ->orWhere('product_code', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->join('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
                            ->join('products', 'products.id', '=', 'product_variants.product_id')
                            ->orderBy('products.name_en', $direction)
                            ->select('stock_levels.*');
                    }),
                Tables\Columns\TextColumn::make('variant.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('variant.barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('variant_label')
                    ->label('Variant')
                    ->state(function (StockLevel $record): string {
                        $parts = array_filter([
                            $record->variant?->color?->name_en ?? $record->variant?->color?->code,
                            $record->variant?->size?->name ?? $record->variant?->size?->code,
                        ]);

                        return $parts !== [] ? implode(' / ', $parts) : ('#'.$record->product_variant_id);
                    }),
                Tables\Columns\TextColumn::make('variant.color.name_en')
                    ->label('Color')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('variant.size.name')
                    ->label('Size')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Warehouse')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_on_hand')
                    ->label('On hand')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity_reserved')
                    ->label('Reserved')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Available')
                    ->numeric(decimalPlaces: 0)
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('(quantity_on_hand - quantity_reserved) '.$direction);
                    }),
                Tables\Columns\TextColumn::make('stock_status')
                    ->label('Status')
                    ->badge()
                    ->state(function (StockLevel $record): string {
                        return InventoryStatus::badge(
                            (float) $record->quantity_on_hand,
                            (float) $record->quantity_reserved,
                        )['label'];
                    })
                    ->color(function (StockLevel $record): string {
                        return InventoryStatus::badge(
                            (float) $record->quantity_on_hand,
                            (float) $record->quantity_reserved,
                        )['color'];
                    }),
                Tables\Columns\TextColumn::make('variant.product.source')
                    ->label('Source')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('synced_at')
                    ->label('Last synced')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name'),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Product')
                    ->options(fn () => \App\Models\Product::query()->orderBy('name_en')->pluck('name_en', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('variant', fn (Builder $variantQuery) => $variantQuery->where('product_id', $data['value']));
                    }),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(fn () => \App\Models\Category::query()->orderBy('name_en')->pluck('name_en', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('variant.product', fn (Builder $productQuery) => $productQuery->where('category_id', $data['value']));
                    }),
                Tables\Filters\SelectFilter::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => \App\Models\Brand::query()->orderBy('name_en')->pluck('name_en', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('variant.product', fn (Builder $productQuery) => $productQuery->where('brand_id', $data['value']));
                    }),
                Tables\Filters\TernaryFilter::make('product_active')
                    ->label('Active product')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('variant.product', fn (Builder $productQuery) => $productQuery->where('is_active', true)),
                        false: fn (Builder $query) => $query->whereHas('variant.product', fn (Builder $productQuery) => $productQuery->where('is_active', false)),
                    ),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low stock')
                    ->query(fn (Builder $query) => $query->whereRaw(
                        '(quantity_on_hand - quantity_reserved) > 0 AND (quantity_on_hand - quantity_reserved) <= ?',
                        [InventoryStatus::lowStockThreshold()],
                    )),
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of stock')
                    ->query(fn (Builder $query) => $query->whereRaw('(quantity_on_hand - quantity_reserved) <= 0')),
                Tables\Filters\SelectFilter::make('stock_source')
                    ->label('Stock source')
                    ->options([
                        'phoenix' => 'Phoenix synced',
                        'manual' => 'Manual product',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'phoenix' => $query->whereNotNull('synced_at'),
                            'manual' => $query->whereHas('variant.product', fn (Builder $productQuery) => $productQuery->where('source', ProductSource::Manual->value)),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Action::make('adjust_stock')
                    ->label('Adjust stock')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isAdmin())
                    ->authorize(fn (): bool => auth()->user() instanceof User && auth()->user()->isAdmin())
                    ->form([
                        Forms\Components\Select::make('adjustment_type')
                            ->label('Adjustment type')
                            ->options([
                                StockAdjustmentType::Increase->value => 'Increase',
                                StockAdjustmentType::Decrease->value => 'Decrease',
                                StockAdjustmentType::Set->value => 'Set exact quantity',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(500),
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference / note')
                            ->maxLength(255),
                    ])
                    ->action(function (StockLevel $record, array $data): void {
                        /** @var User $user */
                        $user = auth()->user();

                        app(StockAdjustmentService::class)->adjust(
                            stockLevelId: $record->id,
                            type: StockAdjustmentType::from($data['adjustment_type']),
                            quantity: (float) $data['quantity'],
                            reason: (string) $data['reason'],
                            reference: $data['reference'] ?? null,
                            user: $user,
                        );
                    }),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['variant.product.category', 'variant.product.brand', 'variant.color', 'variant.size', 'warehouse'])
            ->select('stock_levels.*')
            ->selectRaw('(quantity_on_hand - quantity_reserved) as available_quantity');
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
            'index' => Pages\ListStockLevels::route('/'),
        ];
    }
}
