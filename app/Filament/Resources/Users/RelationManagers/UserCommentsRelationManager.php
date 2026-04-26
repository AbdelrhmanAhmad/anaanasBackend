<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Posts\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('post_id')
                    ->label('Post')
                    ->sortable()
                    ->url(fn ($record) => PostResource::getUrl('view', ['record' => $record->post_id]))
                    ->openUrlInNewTab(),
                TextColumn::make('body')->limit(60)->wrap(),
                TextColumn::make('parent_id')->placeholder('—'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
