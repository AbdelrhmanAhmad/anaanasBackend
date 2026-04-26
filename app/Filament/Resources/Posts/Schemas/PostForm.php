<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make(__('Reference'))
                    ->description(__('MySQL row + Mongo `post_data` / reactions stay in sync with the public API. After saving, verify gallery and attributes in relation tabs on the view page.'))
                    ->icon(Heroicon::InformationCircle)
                    ->color('info'),

                Tabs::make('postEditor')
                    ->vertical()
                    ->tabs([
                        Tab::make(__('Classification'))
                            ->icon(Heroicon::Squares2x2)
                            ->schema([
                                Section::make(__('Taxonomy & placement'))
                                    ->description(__('Owner, hierarchy, geography, and listing type.'))
                                    ->icon(Heroicon::MapPin)
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('user_id')
                                                ->relationship('user', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required(),

                                            Select::make('post_type')
                                                ->options([
                                                    'listing' => 'Listing',
                                                    'auction' => 'Auction',
                                                ])
                                                ->native(false)
                                                ->placeholder('—'),

                                            Select::make('section_id')
                                                ->relationship('section', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->live(),

                                            Select::make('category_id')
                                                ->relationship(
                                                    'category',
                                                    'name',
                                                    fn (Builder $query, Get $get): Builder => $query->when(
                                                        filled($get('section_id')),
                                                        fn (Builder $q) => $q->where('section_id', (int) $get('section_id')),
                                                    ),
                                                )
                                                ->searchable()
                                                ->preload()
                                                ->required(),

                                            Select::make('country_id')
                                                ->relationship('country', 'name')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->live(),

                                            Select::make('city_id')
                                                ->relationship(
                                                    'city',
                                                    'name',
                                                    fn (Builder $query, Get $get): Builder => $query->when(
                                                        filled($get('country_id')),
                                                        fn (Builder $q) => $q->where('country_id', (int) $get('country_id')),
                                                    ),
                                                )
                                                ->searchable()
                                                ->preload()
                                                ->required(),
                                        ]),
                                    ]),
                            ]),

                        Tab::make(__('Content & commerce'))
                            ->icon(Heroicon::DocumentText)
                            ->schema([
                                Section::make(__('Listing content'))
                                    ->description(__('Title, description, price, and publication metadata.'))
                                    ->icon(Heroicon::PencilSquare)
                                    ->schema([
                                        Grid::make(1)->schema([
                                            TextInput::make('title')
                                                ->required()
                                                ->maxLength(255)
                                                ->columnSpanFull(),

                                            Textarea::make('description')
                                                ->rows(8)
                                                ->columnSpanFull(),
                                        ]),

                                        Grid::make(3)->schema([
                                            TextInput::make('price')
                                                ->numeric()
                                                ->prefix('$'),

                                            TextInput::make('status')
                                                ->required()
                                                ->default('active'),

                                            DateTimePicker::make('publish_date')
                                                ->seconds(false)
                                                ->native(false),
                                        ]),
                                    ]),

                                Section::make(__('Media & location'))
                                    ->description(__('Main image path (S3/public) and optional JSON location.'))
                                    ->icon(Heroicon::Photo)
                                    ->schema([
                                        TextInput::make('main_image')
                                            ->label(__('Main image path / URL'))
                                            ->columnSpanFull(),

                                        KeyValue::make('location')
                                            ->keyLabel(__('Key'))
                                            ->valueLabel(__('Value'))
                                            ->addActionLabel(__('Add field'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
