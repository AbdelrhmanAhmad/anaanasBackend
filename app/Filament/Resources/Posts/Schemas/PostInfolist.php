<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Filament\Support\PostDataInfolistFormatter;
use App\Models\Post;
use App\Models\PostEvent;
use App\Models\PostReaction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;

class PostInfolist
{
    protected static function formatJson(mixed $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        if (is_array($state)) {
            return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $state;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Callout::make(__('Listing overview'))
                    ->description(__('Read-only snapshot: MySQL post row, related counts, Mongo analytics, and embedded `post_data`. Use relation tabs below for full lists.'))
                    ->icon(Heroicon::InformationCircle)
                    ->color('gray'),

                Tabs::make('postDetails')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('Classification'))
                            ->icon(Heroicon::Squares2x2)
                            ->schema([
                                Section::make(__('Listing identity'))
                                    ->description(__('Core identifiers and publication state.'))
                                    ->icon(Heroicon::RectangleStack)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('id')->label('ID'),
                                        TextEntry::make('post_type')
                                            ->badge()
                                            ->placeholder('listing'),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('user.name')->label(__('Owner')),
                                    ]),

                                Section::make(__('Taxonomy & geography'))
                                    ->description(__('Where this listing appears in the catalog.'))
                                    ->icon(Heroicon::MapPin)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('section.name')->label(__('Section')),
                                        TextEntry::make('category.name')->label(__('Category')),
                                        TextEntry::make('country.name')->label(__('Country'))->placeholder('—'),
                                        TextEntry::make('city.name')->label(__('City'))->placeholder('—'),
                                    ]),
                            ]),

                        Tab::make(__('Content & commerce'))
                            ->icon(Heroicon::DocumentText)
                            ->schema([
                                Section::make(__('Listing content'))
                                    ->description(__('Title, description, price, and publication metadata.'))
                                    ->icon(Heroicon::PencilSquare)
                                    ->columns(3)
                                    ->schema([
                                        TextEntry::make('title')
                                            ->columnSpanFull(),
                                        TextEntry::make('description')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                        TextEntry::make('price')
                                            ->money('USD')
                                            ->placeholder('—'),
                                        TextEntry::make('publish_date')
                                            ->label(__('Publish date'))
                                            ->dateTime()
                                            ->placeholder('—'),
                                        TextEntry::make('deleted_at')
                                            ->dateTime()
                                            ->placeholder('—')
                                            ->visible(fn (Post $record): bool => $record->trashed()),
                                    ]),

                                Section::make(__('Media & location'))
                                    ->description(__('Main image and optional geo metadata.'))
                                    ->icon(Heroicon::Photo)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('main_image')
                                            ->label(__('Path / URL'))
                                            ->placeholder('—')
                                            ->columnSpanFull()
                                            ->copyable()
                                            ->url(fn (?string $state): ?string => $state && Str::startsWith($state, ['http://', 'https://']) ? $state : null)
                                            ->openUrlInNewTab(),
                                        ImageEntry::make('main_image_preview')
                                            ->label(__('Preview'))
                                            ->state(fn (Post $record): ?string => filled($record->main_image) && Str::startsWith((string) $record->main_image, ['http://', 'https://'])
                                                ? (string) $record->main_image
                                                : null)
                                            ->visible(fn (Post $record): bool => filled($record->main_image) && Str::startsWith((string) $record->main_image, ['http://', 'https://']))
                                            ->height(200)
                                            ->columnSpanFull(),
                                        KeyValueEntry::make('location')
                                            ->label(__('Location'))
                                            ->placeholder(__('—'))
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('Timestamps'))
                                    ->icon(Heroicon::Clock)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('created_at')->dateTime(),
                                        TextEntry::make('updated_at')->dateTime(),
                                    ]),
                            ]),

                        Tab::make(__('MySQL & relations'))
                            ->icon(Heroicon::CircleStack)
                            ->schema([
                                Section::make(__('Related counts'))
                                    ->description(__('Live counts from MySQL relations. Open relation tabs below for full records.'))
                                    ->icon(Heroicon::ChartBarSquare)
                                    ->schema([
                                        KeyValueEntry::make('stats_mysql')
                                            ->label(__('Summary'))
                                            ->state(function (Post $record): array {
                                                $counts = [
                                                    __('Images') => (string) $record->postImages()->count(),
                                                ];

                                                if (SchemaFacade::hasTable('comments')) {
                                                    $counts[__('Comments')] = (string) $record->comments()->count();
                                                }

                                                return $counts;
                                            }),
                                    ]),
                            ]),

                        Tab::make(__('MongoDB & analytics'))
                            ->icon(Heroicon::Bolt)
                            ->schema([
                                Section::make(__('Reactions'))
                                    ->description(__('Aggregated from Mongo collection `post_reactions`.'))
                                    ->icon(Heroicon::HandThumbUp)
                                    ->schema([
                                        KeyValueEntry::make('reaction_by_type')
                                            ->label(__('By type'))
                                            ->state(function (Post $record): array {
                                                $raw = PostReaction::query()
                                                    ->where('post_id', (int) $record->id)
                                                    ->get(['type'])
                                                    ->groupBy('type')
                                                    ->map(fn ($g) => count($g))
                                                    ->all();

                                                $out = [];
                                                $sum = 0;
                                                foreach (PostReaction::allowedTypes() as $t) {
                                                    $n = (int) ($raw[$t] ?? 0);
                                                    $sum += $n;
                                                    $out[$t] = (string) $n;
                                                }

                                                return array_merge([__('Total') => (string) $sum], $out);
                                            }),
                                        TextEntry::make('reaction_documents')
                                            ->label(__('Recent documents (max 30)'))
                                            ->state(function (Post $record): array {
                                                $rows = PostReaction::query()
                                                    ->where('post_id', (int) $record->id)
                                                    ->limit(30)
                                                    ->get()
                                                    ->map(fn ($r) => $r->getAttributes())
                                                    ->values()
                                                    ->all();

                                                return $rows ?: [];
                                            })
                                            ->formatStateUsing(fn ($state) => self::formatJson($state))
                                            ->placeholder('—')
                                            ->copyable()
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('Analytics events'))
                                    ->description(__('From Mongo `post_events` (aggregated by event name).'))
                                    ->icon(Heroicon::PresentationChartLine)
                                    ->schema([
                                        KeyValueEntry::make('events_breakdown')
                                            ->label(__('Counts by event'))
                                            ->state(function (Post $record): array {
                                                try {
                                                    $cursor = PostEvent::raw(function ($collection) use ($record) {
                                                        return $collection->aggregate([
                                                            ['$match' => ['post_id' => (int) $record->id]],
                                                            ['$group' => ['_id' => '$event', 'count' => ['$sum' => 1]]],
                                                            ['$sort' => ['count' => -1]],
                                                        ]);
                                                    });
                                                    $map = [];
                                                    foreach ($cursor as $row) {
                                                        $event = $row->_id ?? 'unknown';
                                                        $map[(string) $event] = (string) ((int) ($row->count ?? 0));
                                                    }

                                                    return $map;
                                                } catch (\Throwable) {
                                                    return ['error' => 'unavailable'];
                                                }
                                            })
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('Listing attributes'))
                                    ->description(__('Readable table — same layout as the public ad details page (`attributes_and_options`).'))
                                    ->icon(Heroicon::ListBullet)
                                    ->schema([
                                        KeyValueEntry::make('post_data_attributes')
                                            ->label(__('Attributes'))
                                            ->keyLabel(__('Attribute'))
                                            ->valueLabel(__('Value'))
                                            ->state(fn (Post $record): array => PostDataInfolistFormatter::attributesKeyValue($record))
                                            ->placeholder(__('No attributes stored in post_data'))
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('post_data metadata'))
                                    ->description(__('MongoDB IDs, embedded publisher snapshot, and timestamps.'))
                                    ->icon(Heroicon::ServerStack)
                                    ->collapsed()
                                    ->schema([
                                        KeyValueEntry::make('post_data_meta')
                                            ->label(__('Metadata'))
                                            ->state(fn (Post $record): array => PostDataInfolistFormatter::metadataKeyValue($record))
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('Raw JSON document'))
                                    ->description(__('Full MongoDB payload for debugging — not shown to end users.'))
                                    ->icon(Heroicon::CodeBracket)
                                    ->collapsed()
                                    ->schema([
                                        TextEntry::make('mongo_post_data_raw')
                                            ->label(__('Document'))
                                            ->state(function (Post $record): array {
                                                $doc = PostDataInfolistFormatter::fetchDocument($record);

                                                return $doc ?? ['error' => __('Mongo unavailable or misconfigured')];
                                            })
                                            ->formatStateUsing(fn ($state) => self::formatJson($state))
                                            ->placeholder('—')
                                            ->copyable()
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
