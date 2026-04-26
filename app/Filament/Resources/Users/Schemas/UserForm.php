<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
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
            ->components([
                Callout::make(__('User administration'))
                    ->description(__('Blocking a user takes effect on API login (`is_blocked`). Revoke API tokens from the “API tokens” tab on the view page when needed.'))
                    ->icon(Heroicon::ShieldCheck)
                    ->color('warning'),

                Tabs::make('userEditor')
                    ->vertical()
                    ->tabs([
                        Tab::make(__('Profile'))
                            ->icon(Heroicon::User)
                            ->schema([
                                Section::make(__('Display & identity'))
                                    ->description(__('Public name and optional username.'))
                                    ->icon(Heroicon::Identification)
                                    ->schema([
                                        Grid::make(1)->schema([
                                            TextInput::make('name')
                                                ->required()
                                                ->maxLength(255)
                                                ->columnSpanFull(),
                                        ]),
                                        Grid::make(2)->schema([
                                            TextInput::make('first_name'),
                                            TextInput::make('last_name'),
                                            TextInput::make('username')
                                                ->unique(ignoreRecord: true),
                                        ]),
                                    ]),

                                Section::make(__('Contact'))
                                    ->description(__('Email and mobile used for login and notifications.'))
                                    ->icon(Heroicon::Envelope)
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('email')
                                                ->label(__('Email'))
                                                ->email()
                                                ->unique(ignoreRecord: true),

                                            TextInput::make('mobile')
                                                ->tel(),
                                        ]),
                                        Grid::make(2)->schema([
                                            Select::make('mobile_verified')
                                                ->label(__('Mobile verified'))
                                                ->options([
                                                    '0' => __('No'),
                                                    '1' => __('Yes'),
                                                ])
                                                ->native(false)
                                                ->default('0'),
                                        ]),
                                    ]),

                                Section::make(__('About'))
                                    ->description(__('Bio and media paths (S3 / public URLs).'))
                                    ->icon(Heroicon::ChatBubbleBottomCenterText)
                                    ->schema([
                                        Textarea::make('bio')
                                            ->rows(5)
                                            ->columnSpanFull(),
                                        Grid::make(2)->schema([
                                            DatePicker::make('date_of_birth')
                                                ->native(false),
                                        ]),
                                        Grid::make(1)->schema([
                                            TextInput::make('avatar')
                                                ->label(__('Avatar path'))
                                                ->columnSpanFull(),
                                            TextInput::make('cover_image')
                                                ->label(__('Cover image path'))
                                                ->columnSpanFull(),
                                        ]),
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
                                    ->description(__('Suspended users cannot obtain a new API session.'))
                                    ->icon(Heroicon::NoSymbol)
                                    ->schema([
                                        Toggle::make('is_blocked')
                                            ->label(__('Blocked (ban)'))
                                            ->inline(false),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
