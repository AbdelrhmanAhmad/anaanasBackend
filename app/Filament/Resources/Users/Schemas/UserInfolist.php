<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
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
            ->columns(1)
            ->components([
                Callout::make(__('Account overview'))
                    ->description(__('Read-only snapshot of the user account. Use relation tabs below for posts, comments, and API tokens.'))
                    ->icon(Heroicon::InformationCircle)
                    ->color('gray'),

                Tabs::make('userDetails')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(__('Profile'))
                            ->icon(Heroicon::User)
                            ->schema([
                                Section::make(__('Display & identity'))
                                    ->description(__('Public name and optional username shown across the platform.'))
                                    ->icon(Heroicon::Identification)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('id')->label('ID'),
                                        TextEntry::make('name'),
                                        TextEntry::make('first_name')->placeholder('—'),
                                        TextEntry::make('last_name')->placeholder('—'),
                                        TextEntry::make('username')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('About & media'))
                                    ->description(__('Bio, birthday, and stored media paths.'))
                                    ->icon(Heroicon::Photo)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('bio')
                                            ->placeholder('—')
                                            ->columnSpanFull(),
                                        TextEntry::make('date_of_birth')
                                            ->date()
                                            ->placeholder('—'),
                                        IconEntry::make('allow_team_invites')
                                            ->label(__('Allow team invites'))
                                            ->boolean(),
                                        TextEntry::make('avatar')
                                            ->label(__('Avatar path'))
                                            ->placeholder('—')
                                            ->copyable()
                                            ->columnSpanFull(),
                                        TextEntry::make('cover_image')
                                            ->label(__('Cover image path'))
                                            ->placeholder('—')
                                            ->copyable()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make(__('Contact & verification'))
                            ->icon(Heroicon::Envelope)
                            ->schema([
                                Section::make(__('Login identifiers'))
                                    ->description(__('Email and mobile used for sign-in and notifications.'))
                                    ->icon(Heroicon::AtSymbol)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('email')
                                            ->label(__('Email'))
                                            ->placeholder('—')
                                            ->copyable(),
                                        TextEntry::make('pending_email')
                                            ->label(__('Pending email'))
                                            ->placeholder('—')
                                            ->copyable(),
                                        TextEntry::make('mobile')
                                            ->placeholder('—')
                                            ->copyable(),
                                        TextEntry::make('mobile_verified')
                                            ->label(__('Mobile verified'))
                                            ->badge()
                                            ->formatStateUsing(fn ($state) => in_array((string) $state, ['1', 'true', 'yes'], true) ? __('Yes') : __('No')),
                                    ]),

                                Section::make(__('Verification status'))
                                    ->description(__('Email confirmation and platform verification badge.'))
                                    ->icon(Heroicon::CheckBadge)
                                    ->columns(2)
                                    ->schema([
                                        TextEntry::make('email_verified_at')
                                            ->label(__('Email verified at'))
                                            ->dateTime()
                                            ->placeholder(__('Not verified')),
                                        TextEntry::make('account_verified_at')
                                            ->label(__('Account verified at'))
                                            ->dateTime()
                                            ->placeholder('—'),
                                        IconEntry::make('is_account_verified')
                                            ->label(__('Account verified'))
                                            ->boolean()
                                            ->trueIcon(Heroicon::CheckBadge)
                                            ->trueColor('success')
                                            ->falseIcon(Heroicon::XCircle)
                                            ->falseColor('gray'),
                                    ]),
                            ]),

                        Tab::make(__('Security & activity'))
                            ->icon(Heroicon::LockClosed)
                            ->schema([
                                Section::make(__('Moderation'))
                                    ->description(__('Access control and content publishing rules.'))
                                    ->icon(Heroicon::NoSymbol)
                                    ->columns(2)
                                    ->schema([
                                        IconEntry::make('is_blocked')
                                            ->label(__('Blocked (ban)'))
                                            ->boolean()
                                            ->trueIcon(Heroicon::NoSymbol)
                                            ->trueColor('danger')
                                            ->falseIcon(Heroicon::CheckCircle)
                                            ->falseColor('success'),
                                        IconEntry::make('auto_approve_posts')
                                            ->label(__('Auto-approve ads'))
                                            ->boolean()
                                            ->trueIcon(Heroicon::CheckCircle)
                                            ->trueColor('success')
                                            ->falseIcon(Heroicon::Clock)
                                            ->falseColor('gray'),
                                    ]),

                                Section::make(__('Activity counts'))
                                    ->description(__('Live counts from the database. Open relation tabs below for full records.'))
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
