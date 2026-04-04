<?php

namespace App\Filament\Resources\Countries\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CountryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([





                        Section::make('بيانات الدوله')
                            ->description('ادخل البيانات بشكل صحيح')
                            ->schema([
                                TranslatableTabs::make('anyLabel')
                                    ->schema([
                                        TextInput::make('name') ->required()

                                    ]),




                                TextInput::make('iso2') ->required( ) ,
                                TextInput::make('iso_code') ->required()  ,
                                Toggle::make('is_active')
                                    ->required()
                                    ->default(false),


                                FileUpload::make('flag'),
                                FileUpload::make('banner'),


                            ])






            ]);
    }
}
