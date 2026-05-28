<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\Admin\OrderFulfillmentService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function form(Form $form): Form
    {
        // No edit/create in this phase.
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.phone')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('order_status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('payment_status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('subtotal')->sortable(),
                Tables\Columns\TextColumn::make('delivery_fee')->sortable(),
                Tables\Columns\TextColumn::make('total')->sortable(),
                Tables\Columns\TextColumn::make('phoenix_order_id')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sync_status')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sync_attempts')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('order_status')
                    ->options([
                        'awaiting_payment' => 'awaiting_payment',
                        'paid' => 'paid',
                        'processing' => 'processing',
                        'shipped' => 'shipped',
                        'delivered' => 'delivered',
                        'cancelled' => 'cancelled',
                        'payment_failed' => 'payment_failed',
                    ]),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'pending',
                        'paid' => 'paid',
                        'failed' => 'failed',
                        'cancelled' => 'cancelled',
                        'expired' => 'expired',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from'),
                        \Filament\Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Action::make('mark_processing')
                    ->label('Mark Processing')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->payment_status === 'paid' && $record->order_status === 'paid')
                    ->action(fn (Order $record) => app(OrderFulfillmentService::class)->markProcessing($record->id)),

                Action::make('mark_shipped')
                    ->label('Mark Shipped')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->payment_status === 'paid' && $record->order_status === 'processing')
                    ->action(fn (Order $record) => app(OrderFulfillmentService::class)->markShipped($record->id)),

                Action::make('mark_delivered')
                    ->label('Mark Delivered')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->payment_status === 'paid' && $record->order_status === 'shipped')
                    ->action(fn (Order $record) => app(OrderFulfillmentService::class)->markDelivered($record->id)),

                Action::make('cancel')
                    ->label('Cancel Order')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Order $record) => $record->payment_status === 'paid' && in_array($record->order_status, ['paid', 'processing'], true))
                    ->action(fn (Order $record) => app(OrderFulfillmentService::class)->cancel($record->id)),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer']);
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}

