<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Sanctum personal access tokens (API "sessions").
 * Revoking a row logs the user out of that token.
 */
class AccessTokensRelationManager extends RelationManager
{
    protected static string $relationship = 'tokens';

    protected static ?string $title = 'جلسات API والتوكنات';

    protected static ?string $modelLabel = 'توكن';

    protected static ?string $pluralModelLabel = 'توكنات';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('الجهاز / الاسم')
                    ->searchable(),
                TextColumn::make('abilities')
                    ->label('الصلاحيات')
                    ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state) : (string) $state)
                    ->limit(40)
                    ->tooltip(fn ($state) => is_array($state) ? json_encode($state) : (string) $state),
                TextColumn::make('last_used_at')
                    ->label('آخر استخدام')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('expires_at')
                    ->label('تاريخ الانتهاء')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('إلغاء'),
            ]);
    }
}
