<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use App\Models\PostData;
use App\Models\PostEvent;
use App\Models\PostReaction;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Str;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make(__('Listing overview'))
                    ->description(__('Read-only snapshot: MySQL post row, related counts, Mongo analytics, and embedded `post_data`.'))
                    ->icon(Heroicon::InformationCircle)
                    ->color('gray'),

                Tabs::make('postDetails')
                    ->tabs([
                        Tab::make(__('Summary'))
                            ->icon(Heroicon::Eye)
                            ->schema([
                                Section::make(__('Identity'))
                                    ->icon(Heroicon::RectangleStack)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('id')->label('ID'),
                                        TextEntry::make('post_type')
                                            ->badge()
                                            ->placeholder('listing'),
                                        TextEntry::make('status')->badge(),
                                        TextEntry::make('user.name')->label(__('Owner')),
                                        TextEntry::make('section.name')->label(__('Section')),
                                        TextEntry::make('category.name')->label(__('Category')),
                                        TextEntry::make('country.name')->label(__('Country'))->placeholder('—'),
                                        TextEntry::make('city.name')->label(__('City'))->placeholder('—'),
                                    ]),

                                Section::make(__('Content'))
                                    ->icon(Heroicon::DocumentText)
                                    ->schema([
                                        TextEntry::make('title')->columnSpanFull(),
                                        TextEntry::make('description')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                        Grid::make(3)->schema([
                                            TextEntry::make('price')->money('USD')->placeholder('—'),
                                            TextEntry::make('publish_date')->dateTime()->placeholder('—'),
                                            TextEntry::make('created_at')->dateTime(),
                                            TextEntry::make('updated_at')->dateTime(),
                                            TextEntry::make('deleted_at')
                                                ->dateTime()
                                                ->visible(fn (Post $record): bool => $record->trashed()),
                                        ]),
                                    ]),

                                Section::make(__('Media'))
                                    ->icon(Heroicon::Photo)
                                    ->schema([
                                        TextEntry::make('main_image')
                                            ->label(__('Path'))
                                            ->placeholder('—')
                                            ->columnSpanFull()
                                            ->url(fn (?string $state): ?string => $state && Str::startsWith($state, ['http://', 'https://']) ? $state : null)
                                            ->openUrlInNewTab(),
                                        ImageEntry::make('main_image_preview')
                                            ->label(__('Preview'))
                                            ->state(fn (Post $record): ?string => filled($record->main_image) && Str::startsWith((string) $record->main_image, ['http://', 'https://'])
                                                ? (string) $record->main_image
                                                : null)
                                            ->visible(fn (Post $record): bool => filled($record->main_image) && Str::startsWith((string) $record->main_image, ['http://', 'https://']))
                                            ->height(200),
                                        KeyValueEntry::make('location')
                                            ->label(__('Location'))
                                            ->placeholder(__('—')),
                                    ]),
                            ]),

                        Tab::make(__('MySQL & relations'))
                            ->icon(Heroicon::CircleStack)
                            ->schema([
                                Section::make(__('Related counts'))
                                    ->description(__('From MySQL relations (see tabs below for full lists).'))
                                    ->icon(Heroicon::ChartBarSquare)
                                    ->schema([
                                        TextEntry::make('stats_mysql')
                                            ->label(__('Counts'))
                                            ->state(function (Post $record): string {
                                                $lines = [];
                                                if (SchemaFacade::hasTable('comments')) {
                                                    $lines[] = __('Comments').': '.$record->comments()->count();
                                                }
                                                $lines[] = __('Images').': '.$record->postImages()->count();

                                                return implode("\n", $lines);
                                            })
                                            ->columnSpanFull(),
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

                                                return array_merge(['total' => (string) $sum], $out);
                                            }),
                                        CodeEntry::make('reaction_documents')
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
                                            ->placeholder('—')
                                            ->copyable(),
                                    ]),

                                Section::make(__('Analytics events'))
                                    ->description(__('From Mongo `post_events` (aggregated by event name).'))
                                    ->icon(Heroicon::PresentationChartLine)
                                    ->schema([
                                        CodeEntry::make('events_breakdown')
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
                                                        $map[(string) $event] = (int) ($row->count ?? 0);
                                                    }

                                                    return $map;
                                                } catch (\Throwable) {
                                                    return ['error' => 'unavailable'];
                                                }
                                            })
                                            ->placeholder('—')
                                            ->copyable(),
                                    ]),

                                Section::make(__('post_data document'))
                                    ->description(__('MongoDB collection `posts` — attributes & denormalised payload.'))
                                    ->icon(Heroicon::ServerStack)
                                    ->schema([
                                        CodeEntry::make('mongo_post_data')
                                            ->label(__('Document'))
                                            ->state(function (Post $record): array {
                                                try {
                                                    $doc = PostData::query()
                                                        ->where('post_id', (int) $record->id)
                                                        ->first();

                                                    if (! $doc) {
                                                        return [];
                                                    }

                                                    $arr = $doc->toArray();
                                                    unset($arr['_id']);

                                                    return $arr;
                                                } catch (\Throwable) {
                                                    return ['error' => __('Mongo unavailable or misconfigured')];
                                                }
                                            })
                                            ->placeholder('—')
                                            ->copyable(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
