<?php

namespace App\Filament\Resources\HomeSliders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HomeSlidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                ImageColumn::make('image_desktop_ar_url')
                    ->label('Desktop AR')
                    ->square()
                    ->size(64),

                ImageColumn::make('image_desktop_en_url')
                    ->label('Desktop EN')
                    ->square()
                    ->size(64),

                ImageColumn::make('image_mobile_ar_url')
                    ->label('Mobile AR')
                    ->square()
                    ->size(64)
                    ->toggleable(),

                ImageColumn::make('image_mobile_en_url')
                    ->label('Mobile EN')
                    ->square()
                    ->size(64)
                    ->toggleable(),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('url')
                    ->label('الرابط')
                    ->limit(28)
                    ->toggleable()
                    ->url(fn ($record) => $record?->url, true),

                TextColumn::make('country.name')
                    ->label('الدولة')
                    ->placeholder('— عام —')
                    ->badge()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label('يبدأ في')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ends_at')
                    ->label('ينتهي في')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('آخر تعديل')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('نشط'),
                SelectFilter::make('country_id')
                    ->label('الدولة')
                    ->relationship('country', 'name')
                    ->preload()
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
