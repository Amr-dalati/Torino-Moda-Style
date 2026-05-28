<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentWebhookResource\Pages;
use App\Models\PaymentWebhook;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentWebhookResource extends Resource
{
    protected static ?string $model = PaymentWebhook::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('event_type')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('processing_status')->badge()->sortable(),
                Tables\Columns\IconColumn::make('signature_valid')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('received_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('processed_at')->dateTime()->sortable(),
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
            'index' => Pages\ListPaymentWebhooks::route('/'),
            'view' => Pages\ViewPaymentWebhook::route('/{record}'),
        ];
    }
}

