<?php

namespace App\Filament\Resources\Sections\Schemas;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([


                Section::make('Cart')
                    ->description('The items you have selected for purchase')
                    ->icon(Heroicon::ShoppingBag)
                    ->schema([


                        TranslatableTabs::make('name')
                            ->schema([
                                TextInput::make('name')->required(),
                            ]) ,

                        TextInput::make('slug')->unique()
                            ->required(),


                        // Permanent storage on S3; Livewire temp uses local disk (see config/livewire.php).
                        FileUpload::make('icon')->directory('sections')->disk('s3')
                                ->visibility('public')->imageEditorAspectRatioOptions([ '1:1', ])->imageAspectRatio('1:1')
                            ->automaticallyResizeImagesToWidth('250')
                            ->automaticallyResizeImagesToHeight('250') ->maxSize(1024*2)

                            ->image()->imageEditor()->maxImageWidth(150)->optimize('webp', 85),
                        FileUpload::make('image')->directory('sections')->disk('s3')
                            ->visibility('public')->imageEditorAspectRatioOptions([ '16:9', ])
                            ->image() ->imageEditor()->optimize('webp', 85),

                        
                        Toggle::make('is_active')   ->required(),




                    ])

            /*        ->footer([
                        Action::make('save'),
                    ])*/

            ]);
    }
}
