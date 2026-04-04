<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Models\Post;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class PostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('section.name')
                    ->label('Section'),
                TextEntry::make('category.name')
                    ->label('Category'),
                TextEntry::make('title'),
                TextEntry::make('price')
                    ->money()
                    ->placeholder('-'),
                TextEntry::make('status'),
                ImageEntry::make('main_image')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Post $record): bool => $record->trashed()),
            ]);
    }
}
