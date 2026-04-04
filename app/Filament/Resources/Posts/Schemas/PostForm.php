<?php

namespace App\Filament\Resources\Posts\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('section_id')
                    ->relationship('section', 'name')
                    ->required(),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                TextInput::make('price')
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('status')
                    ->required()
                    ->default('active'),
                FileUpload::make('main_image')
                    ->image(),
                TextInput::make('location'),
            ]);
    }
}
