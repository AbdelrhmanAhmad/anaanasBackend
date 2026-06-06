<?php

namespace App\Filament\Resources\ForbiddenWords\Pages;

use App\Filament\Resources\ForbiddenWords\ForbiddenWordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageForbiddenWords extends ManageRecords
{
    protected static string $resource = ForbiddenWordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
