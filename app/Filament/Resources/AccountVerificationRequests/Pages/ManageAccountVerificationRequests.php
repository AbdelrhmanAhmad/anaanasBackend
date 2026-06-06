<?php

namespace App\Filament\Resources\AccountVerificationRequests\Pages;

use App\Filament\Resources\AccountVerificationRequests\AccountVerificationRequestResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAccountVerificationRequests extends ManageRecords
{
    protected static string $resource = AccountVerificationRequestResource::class;
}
