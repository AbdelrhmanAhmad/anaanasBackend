<?php

namespace App\Filament\Resources\HomeSliders;

use App\Filament\Resources\HomeSliders\Pages\CreateHomeSlider;
use App\Filament\Resources\HomeSliders\Pages\EditHomeSlider;
use App\Filament\Resources\HomeSliders\Pages\ListHomeSliders;
use App\Filament\Resources\HomeSliders\Schemas\HomeSliderForm;
use App\Filament\Resources\HomeSliders\Tables\HomeSlidersTable;
use App\Models\HomeSlider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HomeSliderResource extends Resource
{
    protected static ?string $model = HomeSlider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Home Slider';

    protected static ?string $modelLabel = 'Home Slide';

    protected static ?string $pluralModelLabel = 'Home Slides';

    public static function form(Schema $schema): Schema
    {
        return HomeSliderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HomeSlidersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHomeSliders::route('/'),
            'create' => CreateHomeSlider::route('/create'),
            'edit' => EditHomeSlider::route('/{record}/edit'),
        ];
    }
}
