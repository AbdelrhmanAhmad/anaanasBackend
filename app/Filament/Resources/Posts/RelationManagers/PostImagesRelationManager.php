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

    protected static ?string $title = 'صور الإعلان';

    protected static ?string $modelLabel = 'صورة';

    protected static ?string $pluralModelLabel = 'صور';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('image')
                ->label('مسار الصورة (S3 / عام)')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('image')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                ImageColumn::make('image_full_url')
                    ->label('معاينة')
                    ->square()
                    ->defaultImageUrl(url('/favicon.ico')),
                TextColumn::make('image')
                    ->label('المسار')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state),
                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
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
