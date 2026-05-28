<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        // Explicitly avoid rendering raw payloads in Filament.
        return $infolist->schema([
            Infolists\Components\TextEntry::make('order.order_number')->label('Order'),
            Infolists\Components\TextEntry::make('provider'),
            Infolists\Components\TextEntry::make('method'),
            Infolists\Components\TextEntry::make('status'),
            Infolists\Components\TextEntry::make('amount'),
            Infolists\Components\TextEntry::make('currency'),
            Infolists\Components\TextEntry::make('merchant_reference'),
            Infolists\Components\TextEntry::make('gateway_payment_id'),
            Infolists\Components\TextEntry::make('checkout_url'),
            Infolists\Components\TextEntry::make('expires_at')->dateTime(),
            Infolists\Components\TextEntry::make('paid_at')->dateTime(),
            Infolists\Components\TextEntry::make('failed_at')->dateTime(),
            Infolists\Components\TextEntry::make('failure_code'),
            Infolists\Components\TextEntry::make('failure_message'),
            Infolists\Components\TextEntry::make('created_at')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('order.order_number')->label('Order')->searchable(),
                Tables\Columns\TextColumn::make('provider')->sortable(),
                Tables\Columns\TextColumn::make('method')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('amount')->sortable(),
                Tables\Columns\TextColumn::make('merchant_reference')->searchable(),
                Tables\Columns\TextColumn::make('paid_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
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
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}

