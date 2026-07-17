<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('account_status')
                ->label('Status')
                ->state(fn (Customer $record): string => $record->isDeleted() ? 'Deleted / Anonymized' : 'Active'),
            Infolists\Components\TextEntry::make('name')
                ->label('Display name')
                ->state(fn (Customer $record): string => $record->isDeleted() ? 'Anonymized' : (string) $record->name),
            Infolists\Components\TextEntry::make('phone')
                ->label('Phone')
                ->state(fn (Customer $record): string => $record->isDeleted() ? 'Anonymized' : (string) $record->phone),
            Infolists\Components\TextEntry::make('email')
                ->label('Email')
                ->state(fn (Customer $record): string => $record->isDeleted() ? 'Anonymized' : (string) ($record->email ?? '—')),
            Infolists\Components\IconEntry::make('is_active')->boolean()->label('Active flag'),
            Infolists\Components\TextEntry::make('deleted_at')->dateTime()->placeholder('—'),
            Infolists\Components\TextEntry::make('anonymized_at')->dateTime()->placeholder('—'),
            Infolists\Components\TextEntry::make('orders_count')
                ->label('Retained orders')
                ->counts('orders'),
            Infolists\Components\TextEntry::make('last_login_at')->dateTime()->placeholder('—'),
            Infolists\Components\TextEntry::make('created_at')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('orders'))
            ->columns([
                Tables\Columns\TextColumn::make('account_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Customer $record): string => $record->isDeleted() ? 'Deleted' : 'Active')
                    ->color(fn (Customer $record): string => $record->isDeleted() ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->state(fn (Customer $record): string => $record->isDeleted() ? 'Anonymized' : (string) $record->name)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner->whereNull('deleted_at')->where('name', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->state(fn (Customer $record): string => $record->isDeleted() ? 'Anonymized' : (string) $record->phone)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner->whereNull('deleted_at')->where('phone', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->state(fn (Customer $record): string => $record->isDeleted() ? 'Anonymized' : (string) ($record->email ?? '—'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('anonymized_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('account_status')
                    ->label('Account status')
                    ->options([
                        'active' => 'Active',
                        'deleted' => 'Deleted',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'active' => $query->whereNull('deleted_at'),
                            'deleted' => $query->whereNotNull('deleted_at'),
                            default => $query,
                        };
                    }),
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
            'index' => Pages\ListCustomers::route('/'),
            'view' => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}
