<?php

namespace App\Filament\Resources\ChatReports;

use App\Filament\Resources\ChatReports\Pages\ListChatReports;
use App\Filament\Resources\ChatReports\Pages\ViewChatReport;
use App\Filament\Resources\ChatReports\Tables\ChatReportsTable;
use App\Models\ChatReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ChatReportResource extends Resource
{
    protected static ?string $model = ChatReport::class;

    /** Match MongoDB `_id` in URLs like `.../chat-reports/{id}`. */
    protected static ?string $recordRouteKeyName = '_id';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'بلاغات المحادثات';

    protected static ?string $modelLabel = 'بلاغ محادثة';

    protected static ?string $pluralModelLabel = 'بلاغات المحادثات';

    protected static ?int $navigationSort = 90;

    public static function form(Schema $schema): Schema
    {
        return $schema; // no creation form — reports come from the API
    }

    public static function table(Table $table): Table
    {
        return ChatReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChatReports::route('/'),
            'view' => ViewChatReport::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            return (string) ChatReport::where('status', ChatReport::STATUS_PENDING)->count();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
