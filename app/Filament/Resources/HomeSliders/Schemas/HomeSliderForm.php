<?php

namespace App\Filament\Resources\HomeSliders\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Slider editor: 4 image variants (desktop/mobile × ar/en) + targeting + scheduling.
 * Files are uploaded to S3 under the `home-sliders/` directory and optimised to webp.
 */
class HomeSliderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات السلايد')
                    ->description('عنوان داخلي + رابط النقر + جدولة')
                    ->icon(Heroicon::Cog6Tooth)
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')
                                ->label('عنوان داخلي (اختياري)')
                                ->maxLength(120)
                                ->columnSpan(2),

                            TextInput::make('url')
                                ->label('رابط النقر (اختياري)')
                                ->placeholder('https://example.com/page or /sections/cars')
                                ->url()
                                ->maxLength(500),

                            Toggle::make('open_in_new_tab')
                                ->label('فتح في تبويب جديد')
                                ->inline(false),

                            Select::make('country_id')
                                ->label('الدولة (اتركها فارغة لجميع الدول)')
                                ->relationship('country', 'name')
                                ->searchable()
                                ->preload()
                                ->nullable(),

                            DateTimePicker::make('starts_at')
                                ->label('يبدأ الظهور في')
                                ->seconds(false)
                                ->nullable(),

                            DateTimePicker::make('ends_at')
                                ->label('ينتهي الظهور في')
                                ->seconds(false)
                                ->nullable()
                                ->after('starts_at'),
                        ]),

                        Grid::make(2)->schema([
                            Toggle::make('is_active')
                                ->label('نشط')
                                ->default(true)
                                ->required(),

                            TextInput::make('sort_order')
                                ->label('ترتيب العرض')
                                ->numeric()
                                ->default(0)
                                ->required(),
                        ]),
                    ])
                    ->columns(1),

                Section::make('الصور')
                    ->description('قم برفع نسخة الديسكتوب والهاتف لكل لغة. النسبة الموصى بها: 16:6 للديسكتوب، 4:3 للهاتف.')
                    ->icon(Heroicon::Photo)
                    ->schema([
                        Tabs::make('images')
                            ->tabs([
                                Tab::make('عربي')
                                    ->icon(Heroicon::Language)
                                    ->schema([
                                        Grid::make(2)->schema([
                                            FileUpload::make('image_desktop_ar')
                                                ->label('صورة الديسكتوب — عربي')
                                                ->directory('home-sliders')
                                                ->disk('s3')
                                                ->visibility('public')
                                                ->image()
                                                ->imageEditor()
                                                ->imageEditorAspectRatioOptions(['16:6', '16:9', '21:9'])
//                                                ->imageAspectRatio('16:6')
                                                ->maxSize(1024 * 4)
                                                ->optimize('webp', 85),

                                            FileUpload::make('image_mobile_ar')
                                                ->label('صورة الهاتف — عربي')
                                                ->directory('home-sliders')
                                                ->disk('s3')
                                                ->visibility('public')
                                                ->image()
                                                ->imageEditor()
                                                ->imageEditorAspectRatioOptions(['4:3', '3:2', '1:1'])
//                                                ->imageAspectRatio('4:3')
                                                ->maxSize(1024 * 4)
                                                ->optimize('webp', 85),
                                        ]),
                                    ]),

                                Tab::make('English')
                                    ->icon(Heroicon::Language)
                                    ->schema([
                                        Grid::make(2)->schema([
                                            FileUpload::make('image_desktop_en')
                                                ->label('Desktop image — English')
                                                ->directory('home-sliders')
                                                ->disk('s3')
                                                ->visibility('public')
                                                ->image()
                                                ->imageEditor()
                                                ->imageEditorAspectRatioOptions(['16:6', '16:9', '21:9'])
//                                                ->imageAspectRatio('16:6')
                                                ->maxSize(1024 * 4)
                                                ->optimize('webp', 85),

                                            FileUpload::make('image_mobile_en')
                                                ->label('Mobile image — English')
                                                ->directory('home-sliders')
                                                ->disk('s3')
                                                ->visibility('public')
                                                ->image()
                                                ->imageEditor()
                                                ->imageEditorAspectRatioOptions(['4:3', '3:2', '1:1'])
//                                                ->imageAspectRatio('4:3')
                                                ->maxSize(1024 * 4)
                                                ->optimize('webp', 85)
                                            ,
                                        ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
