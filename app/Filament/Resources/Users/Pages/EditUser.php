<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\PostModerationTableActions;
use App\Models\Post;
use App\Services\PostModerationService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            PostModerationTableActions::approveAllPendingForUserHeader()
                ->visible(fn (): bool => Post::query()
                    ->where('user_id', (int) $this->record->getKey())
                    ->whereIn('status', Post::pendingReviewStatuses())
                    ->exists())
                ->action(function (): void {
                    $count = app(PostModerationService::class)->approveAllPendingForUser($this->record);
                    Notification::make()
                        ->title("تم قبول {$count} إعلان")
                        ->success()
                        ->send();
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
