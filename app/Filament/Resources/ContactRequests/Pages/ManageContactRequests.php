<?php

namespace App\Filament\Resources\ContactRequests\Pages;

use App\Filament\Resources\ContactRequests\ContactRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageContactRequests extends ManageRecords
{
    protected static string $resource = ContactRequestResource::class;
}
