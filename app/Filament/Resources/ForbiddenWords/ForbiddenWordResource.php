<?php

namespace App\Filament\Resources\ForbiddenWords;

use App\Filament\Resources\ForbiddenWords\Pages\ManageForbiddenWords;
use App\Models\ForbiddenWord;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ForbiddenWordResource extends Resource
{
    protected static ?string $model = ForbiddenWord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'الكلمات الممنوعة';

    protected static ?string $modelLabel = 'كلمة ممنوعة';

    protected static ?string $pluralModelLabel = 'الكلمات الممنوعة';

    protected static string|\UnitEnum|null $navigationGroup = 'الإشراف';

    protected static ?string $recordTitleAttribute = 'word';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('word')
                    ->label('الكلمة')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('category')
                    ->label('التصنيف')
                    ->options(ForbiddenWord::CATEGORIES)
                    ->default('general')
                    ->required()
                    ->searchable(),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('word')
                    ->label('الكلمة')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->label('التصنيف')
                    ->formatStateUsing(fn (?string $state) => ForbiddenWord::CATEGORIES[$state] ?? $state)
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('نشط'),
                SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options(ForbiddenWord::CATEGORIES),
            ])
            ->defaultSort('word')
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

    public static function getPages(): array
    {
        return [
            'index' => ManageForbiddenWords::route('/'),
        ];
    }
}
