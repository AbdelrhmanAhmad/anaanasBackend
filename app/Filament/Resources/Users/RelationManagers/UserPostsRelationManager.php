<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Support\PostModerationTableActions;
use App\Models\Post;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserPostsRelationManager extends RelationManager
{
    protected static string $relationship = 'posts';

    protected static ?string $title = 'إعلانات المستخدم';

    protected static ?string $modelLabel = 'إعلان';

    protected static ?string $pluralModelLabel = 'إعلانات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('title')->label('العنوان')->searchable()->limit(40),
                TextColumn::make('section.name')->label('القسم')->toggleable(),
                TextColumn::make('category.name')->label('التصنيف')->toggleable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Post::STATUS_ACTIVE => 'منشور',
                        Post::STATUS_PENDING_REVIEW, Post::STATUS_LEGACY_PENDING => 'بانتظار المراجعة',
                        Post::STATUS_REJECTED => 'مرفوض',
                        Post::STATUS_INACTIVE => 'غير نشط',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        Post::STATUS_ACTIVE => 'success',
                        Post::STATUS_PENDING_REVIEW, Post::STATUS_LEGACY_PENDING => 'warning',
                        Post::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('post_type')
                    ->label('النوع')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'listing' => 'إعلان',
                        'auction' => 'مزاد',
                        default => $state ?? 'إعلان',
                    }),
                TextColumn::make('price')->label('السعر')->money('USD')->placeholder('—'),
                TextColumn::make('publish_date')->label('تاريخ النشر')->dateTime()->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('تاريخ الإنشاء')->dateTime()->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                PostModerationTableActions::approve(),
                PostModerationTableActions::reject(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
