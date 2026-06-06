<?php

namespace App\Filament\Resources\PendingPostReviews;

use App\Filament\Resources\PendingPostReviews\Pages\ManagePendingPostReviews;
use App\Filament\Support\PostModerationTableActions;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PendingPostReviewResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'مراجعة الإعلانات';

    protected static ?string $modelLabel = 'إعلان بانتظار المراجعة';

    protected static ?string $pluralModelLabel = 'مراجعة الإعلانات';

    protected static string|\UnitEnum|null $navigationGroup = 'الإشراف';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('المستخدم')
                    ->searchable(['users.name', 'users.email', 'users.mobile'])
                    ->description(fn (Post $record) => '#'.$record->user_id),
                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable()
                    ->limit(50)
                    ->wrap(),
                TextColumn::make('section.name')
                    ->label('القسم')
                    ->toggleable(),
                TextColumn::make('category.name')
                    ->label('التصنيف')
                    ->toggleable(),
                TextColumn::make('price')
                    ->label('السعر')
                    ->money('USD')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('تاريخ الإرسال')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                PostModerationTableActions::approve(),
                PostModerationTableActions::reject(),
                PostModerationTableActions::approveAllPendingForPostAuthor(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePendingPostReviews::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->whereIn('status', Post::pendingReviewStatuses())
            ->with(['user', 'section', 'category']);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
