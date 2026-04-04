<?php

namespace App\Filament\Resources\Cities\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CityForm
{
    public static function configure(Schema $schema ,$ownerRecord= null): Schema
    {
        return $schema
            ->components([


                Section::make('بيانات المدينه')
                    ->description('ادخل البيانات بشكل صحيح')
                    ->columnSpan(function() use ($ownerRecord){
                     return  $ownerRecord ?  2 : 1 ;
                    })
                    ->schema([
                        TranslatableTabs::make('anyLabel')
                            ->schema([
                                TextInput::make('name')->required()
                            ]),


                        Select::make('country_id')
                            ->relationship('country', 'name')
                            ->hidden(fn () =>$ownerRecord)
                            ->required(),


                        Toggle::make('is_active')->required(),
                        FileUpload::make('banner'),

                    ])

            ]);
    }
}
