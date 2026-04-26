<?php

namespace App\Filament\Resources\Posts\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PostImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'postImages';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('image')
                ->label('Image path (S3 / public)')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('image')
            ->columns([
                TextColumn::make('id')->sortable(),
                ImageColumn::make('image_full_url')
                    ->label('Preview')
                    ->square()
                    ->defaultImageUrl(url('/favicon.ico')),
                TextColumn::make('image')
                    ->label('Path')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
