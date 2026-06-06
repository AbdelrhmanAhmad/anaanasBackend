<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Callout::make(__('User administration'))
                    ->description(__('Blocking a user takes effect on API login (`is_blocked`). Revoke API tokens from the “API tokens” tab on the view page when needed.'))
                    ->icon(Heroicon::ShieldCheck)
                    ->color('warning'),

                Tabs::make('userEditor')
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
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        TextInput::make('first_name')
                                            ->maxLength(255),
                                        TextInput::make('last_name')
                                            ->maxLength(255),
                                        TextInput::make('username')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('About & media'))
                                    ->description(__('Bio, birthday, and stored media paths (S3 / public URLs).'))
                                    ->icon(Heroicon::Photo)
                                    ->columns(2)
                                    ->schema([
                                        Textarea::make('bio')
                                            ->rows(4)
                                            ->columnSpanFull(),
                                        DatePicker::make('date_of_birth')
                                            ->native(false),
                                        Toggle::make('allow_team_invites')
                                            ->label(__('Allow team invites'))
                                            ->inline(false),
                                        TextInput::make('avatar')
                                            ->label(__('Avatar path'))
                                            ->columnSpanFull(),
                                        TextInput::make('cover_image')
                                            ->label(__('Cover image path'))
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
                                        TextInput::make('email')
                                            ->label(__('Email'))
                                            ->email()
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),
                                        TextInput::make('pending_email')
                                            ->label(__('Pending email'))
                                            ->email()
                                            ->maxLength(255)
                                            ->helperText(__('Set when the user requests an email change; cleared after verification.')),
                                        TextInput::make('mobile')
                                            ->tel()
                                            ->maxLength(255),
                                        Select::make('mobile_verified')
                                            ->label(__('Mobile verified'))
                                            ->options([
                                                '0' => __('No'),
                                                '1' => __('Yes'),
                                            ])
                                            ->native(false)
                                            ->default('0'),
                                    ]),

                                Section::make(__('Verification status'))
                                    ->description(__('Manually confirm email or grant the platform verification badge.'))
                                    ->icon(Heroicon::CheckBadge)
                                    ->columns(2)
                                    ->schema([
                                        DateTimePicker::make('email_verified_at')
                                            ->label(__('Email verified at'))
                                            ->native(false)
                                            ->seconds(false)
                                            ->placeholder(__('Not verified')),
                                        Toggle::make('is_account_verified')
                                            ->label(__('Account verified'))
                                            ->helperText(__('Trusted seller badge on the platform.'))
                                            ->inline(false),
                                    ]),
                            ]),

                        Tab::make(__('Security'))
                            ->icon(Heroicon::LockClosed)
                            ->schema([
                                Section::make(__('Credentials'))
                                    ->description(__('Password is required when creating a user; leave blank when editing to keep the current hash.'))
                                    ->icon(Heroicon::Key)
                                    ->schema([
                                        TextInput::make('password')
                                            ->password()
                                            ->revealable()
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->helperText(__('Required on create. Leave empty when editing to keep the current password.'))
                                            ->columnSpanFull(),
                                    ]),

                                Section::make(__('Moderation'))
                                    ->description(__('Suspended users cannot obtain a new API session. Auto-approved users publish ads without admin review.'))
                                    ->icon(Heroicon::NoSymbol)
                                    ->columns(2)
                                    ->schema([
                                        Toggle::make('is_blocked')
                                            ->label(__('Blocked (ban)'))
                                            ->helperText(__('Prevents new API login sessions.'))
                                            ->inline(false),
                                        Toggle::make('auto_approve_posts')
                                            ->label(__('Auto-approve ads'))
                                            ->helperText(__('New ads publish immediately without review.'))
                                            ->inline(false),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
