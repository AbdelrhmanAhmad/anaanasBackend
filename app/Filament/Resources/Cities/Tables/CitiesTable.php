<?php

namespace App\Filament\Resources\Cities\Tables;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CitiesTable
{
    public static function configure(Table $table ,$ownerRecord= null): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('banner'),

                TextColumn::make('country.name')
                    ->visible(function () use($ownerRecord){
                        return $ownerRecord != null;
                    })
                    ->searchable(),
                TextColumn::make('name') ->searchable(),

                ToggleColumn::make('is_active'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])

            ->headerActions([
                CreateAction::make()->visible(function () use($ownerRecord){return $ownerRecord != null;}),
                AssociateAction::make()->visible(function () use($ownerRecord){return $ownerRecord != null;}),
            ])

            ->filters([
                TrashedFilter::make(),


            ])




            ->recordActions([
                EditAction::make(),

                DissociateAction::make() ->visible(function () use($ownerRecord){return $ownerRecord != null;}),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),



            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make() ->visible(function () use($ownerRecord){return $ownerRecord != null;}),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])

            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]))

            ;
    }
}
