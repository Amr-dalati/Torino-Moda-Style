<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductImage;
use App\Support\Catalog\ProductImageOptimizer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('path')
                    ->label('Image')
                    ->disk('public')
                    ->directory(fn (): string => 'products/'.$this->getOwnerRecord()->getKey())
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => (string) Str::uuid().'.'.$file->getClientOriginalExtension()
                    )
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->image()
                    ->required(),
                Forms\Components\TextInput::make('alt_text')
                    ->maxLength(255),
                Forms\Components\TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Forms\Components\Toggle::make('is_primary')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->disk('public')
                    ->visibility('public'),
                Tables\Columns\TextColumn::make('alt_text'),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
                Tables\Columns\IconColumn::make('is_primary')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['disk'] = 'public';

                        return $data;
                    })
                    ->after(function (ProductImage $record): void {
                        app(ProductImageOptimizer::class)->optimize($record);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['disk'] = 'public';

                        return $data;
                    })
                    ->after(function (ProductImage $record): void {
                        app(ProductImageOptimizer::class)->optimize($record);
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
