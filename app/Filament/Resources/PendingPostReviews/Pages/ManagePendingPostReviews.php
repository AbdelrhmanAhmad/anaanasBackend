<?php

namespace App\Filament\Resources\PendingPostReviews\Pages;

use App\Filament\Resources\PendingPostReviews\PendingPostReviewResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePendingPostReviews extends ManageRecords
{
    protected static string $resource = PendingPostReviewResource::class;
}
