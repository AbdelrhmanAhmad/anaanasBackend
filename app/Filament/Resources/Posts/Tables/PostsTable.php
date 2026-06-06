<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Filament\Support\PostModerationTableActions;
use App\Models\Post;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('المالك')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.name')
                    ->label('القسم')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('country.name')
                    ->label('الدولة')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city.name')
                    ->label('المدينة')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('post_type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'listing' => 'إعلان',
                        'auction' => 'مزاد',
                        default => $state ?? 'إعلان',
                    })
                    ->toggleable(),
                TextColumn::make('price')
                    ->label('السعر')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        Post::STATUS_ACTIVE => 'success',
                        Post::STATUS_PENDING_REVIEW, Post::STATUS_LEGACY_PENDING => 'warning',
                        Post::STATUS_REJECTED => 'danger',
                        Post::STATUS_INACTIVE => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        Post::STATUS_ACTIVE => 'منشور',
                        Post::STATUS_PENDING_REVIEW, Post::STATUS_LEGACY_PENDING => 'بانتظار المراجعة',
                        Post::STATUS_REJECTED => 'مرفوض',
                        Post::STATUS_INACTIVE => 'غير نشط',
                        default => $state,
                    })
                    ->searchable(),
                TextColumn::make('comments_count')
                    ->counts('comments')
                    ->label('التعليقات')
                    ->toggleable()
                    ->visible(fn (): bool => Schema::hasTable('comments')),
                TextColumn::make('publish_date')
                    ->label('تاريخ النشر')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('تاريخ التحديث')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('تاريخ الحذف')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('المحذوفات'),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        Post::STATUS_ACTIVE => 'منشور',
                        Post::STATUS_PENDING_REVIEW => 'بانتظار المراجعة',
                        Post::STATUS_REJECTED => 'مرفوض',
                        Post::STATUS_INACTIVE => 'غير نشط',
                        Post::STATUS_LEGACY_PENDING => 'بانتظار (قديم)',
                    ]),
                SelectFilter::make('post_type')
                    ->label('النوع')
                    ->options([
                        'listing' => 'إعلان',
                        'auction' => 'مزاد',
                    ]),
            ])
            ->recordActions([
                PostModerationTableActions::approve(),
                PostModerationTableActions::reject(),
                PostModerationTableActions::approveAllPendingForPostAuthor(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
