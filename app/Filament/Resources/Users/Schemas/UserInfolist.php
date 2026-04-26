<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
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

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make(__('Account overview'))
                    ->description(__('Use the relation tabs below for posts, comments, and API tokens (sessions).'))
                    ->icon(Heroicon::InformationCircle)
                    ->color('gray'),

                Tabs::make('userDetails')
                    ->tabs([
                        Tab::make(__('Profile'))
                            ->icon(Heroicon::User)
                            ->schema([
                                Section::make(__('Identity'))
                                    ->icon(Heroicon::Identification)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('id'),
                                        TextEntry::make('name'),
                                        TextEntry::make('username')->placeholder('—'),
                                        TextEntry::make('first_name')->placeholder('—'),
                                        TextEntry::make('last_name')->placeholder('—'),
                                        TextEntry::make('date_of_birth')->date()->placeholder('—'),
                                    ]),

                                Section::make(__('Contact'))
                                    ->icon(Heroicon::Envelope)
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('email')
                                                ->label(__('Email'))
                                                ->placeholder('—')
                                                ->copyable(),
                                            TextEntry::make('mobile')
                                                ->placeholder('—')
                                                ->copyable(),
                                        ]),
                                        TextEntry::make('mobile_verified')
                                            ->badge()
                                            ->formatStateUsing(fn ($state) => in_array((string) $state, ['1', 'true', 'yes'], true) ? __('Yes') : __('No')),
                                        TextEntry::make('email_verified_at')
                                            ->dateTime()
                                            ->placeholder('—'),
                                    ]),

                                Section::make(__('About & media'))
                                    ->icon(Heroicon::Photo)
                                    ->schema([
                                        TextEntry::make('bio')
                                            ->columnSpanFull()
                                            ->placeholder('—'),
                                        TextEntry::make('avatar')
                                            ->label(__('Avatar path'))
                                            ->columnSpanFull()
                                            ->placeholder('—'),
                                        TextEntry::make('cover_image')
                                            ->label(__('Cover path'))
                                            ->columnSpanFull()
                                            ->placeholder('—'),
                                    ]),
                            ]),

                        Tab::make(__('Status & activity'))
                            ->icon(Heroicon::ChartBarSquare)
                            ->schema([
                                Section::make(__('Moderation'))
                                    ->icon(Heroicon::NoSymbol)
                                    ->schema([
                                        IconEntry::make('is_blocked')
                                            ->label(__('Blocked'))
                                            ->boolean()
                                            ->trueIcon(Heroicon::NoSymbol)
                                            ->trueColor('danger')
                                            ->falseIcon(Heroicon::CheckCircle)
                                            ->falseColor('success'),
                                    ]),

                                Section::make(__('Counts'))
                                    ->description(__('Live counts from the database.'))
                                    ->icon(Heroicon::CircleStack)
                                    ->schema([
                                        KeyValueEntry::make('activity_counts')
                                            ->label(__('Summary'))
                                            ->state(function (User $record): array {
                                                $posts = $record->posts()->count();
                                                $comments = SchemaFacade::hasTable('comments')
                                                    ? $record->comments()->count()
                                                    : 0;
                                                $tokens = $record->tokens()->count();
                                                $lastToken = $record->tokens()->orderByDesc('last_used_at')->first();
                                                $lastUsed = $lastToken && $lastToken->last_used_at
                                                    ? $lastToken->last_used_at->format('Y-m-d H:i')
                                                    : '—';

                                                return [
                                                    __('Posts') => (string) $posts,
                                                    __('Comments') => (string) $comments,
                                                    __('API tokens') => (string) $tokens,
                                                    __('Last token use') => $lastUsed,
                                                ];
                                            }),
                                    ]),

                                Section::make(__('Timestamps'))
                                    ->icon(Heroicon::Clock)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('created_at')->dateTime(),
                                        TextEntry::make('updated_at')->dateTime(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
