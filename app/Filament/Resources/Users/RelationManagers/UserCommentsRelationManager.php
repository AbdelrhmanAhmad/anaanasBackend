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

    protected static ?string $title = 'تعليقات المستخدم';

    protected static ?string $modelLabel = 'تعليق';

    protected static ?string $pluralModelLabel = 'تعليقات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('body')
                ->label('نص التعليق')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('post_id')
                    ->label('الإعلان')
                    ->sortable()
                    ->url(fn ($record) => PostResource::getUrl('view', ['record' => $record->post_id]))
                    ->openUrlInNewTab(),
                TextColumn::make('body')->label('التعليق')->limit(60)->wrap(),
                TextColumn::make('parent_id')->label('التعليق الأب')->placeholder('—'),
                TextColumn::make('created_at')->label('تاريخ الإنشاء')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
