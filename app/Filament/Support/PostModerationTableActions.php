<?php

namespace App\Filament\Support;

use App\Models\Post;
use App\Models\User;
use App\Services\PostModerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

final class PostModerationTableActions
{
    public static function approve(): Action
    {
        return Action::make('approvePost')
            ->label('قبول')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Post $record): bool => in_array($record->status, Post::pendingReviewStatuses(), true))
            ->requiresConfirmation()
            ->modalHeading('قبول الإعلان')
            ->modalDescription('سيُنشر الإعلان ويظهر للجميع في المنصة.')
            ->action(function (Post $record): void {
                app(PostModerationService::class)->approve($record);
                Notification::make()->title('تم قبول الإعلان')->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('rejectPost')
            ->label('رفض')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Post $record): bool => in_array($record->status, Post::pendingReviewStatuses(), true))
            ->form([
                Textarea::make('admin_notes')
                    ->label('سبب الرفض (اختياري)')
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalHeading('رفض الإعلان')
            ->action(function (Post $record, array $data): void {
                app(PostModerationService::class)->reject($record, $data['admin_notes'] ?? null);
                Notification::make()->title('تم رفض الإعلان')->warning()->send();
            });
    }

    public static function approveAllPendingForPostAuthor(): Action
    {
        return Action::make('approveAllAuthorPosts')
            ->label('قبول كل إعلانات المستخدم')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('قبول كل الإعلانات المعلّقة')
            ->modalDescription('سيتم نشر جميع إعلانات هذا المستخدم التي بانتظار المراجعة.')
            ->action(function (Post $record): void {
                $user = $record->user ?? User::query()->find((int) $record->user_id);
                if (! $user) {
                    Notification::make()->title('المستخدم غير موجود')->danger()->send();

                    return;
                }
                $count = app(PostModerationService::class)->approveAllPendingForUser($user);
                Notification::make()
                    ->title("تم قبول {$count} إعلان")
                    ->success()
                    ->send();
            });
    }

    public static function approveAllPendingForUserHeader(): Action
    {
        return Action::make('approveAllUserPendingPosts')
            ->label('قبول كل الإعلانات المعلّقة')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('قبول كل الإعلانات المعلّقة')
            ->modalDescription('سيتم نشر جميع إعلانات هذا المستخدم التي بانتظار المراجعة.')
            ->action(function (User $record): void {
                $count = app(PostModerationService::class)->approveAllPendingForUser($record);
                Notification::make()
                    ->title("تم قبول {$count} إعلان")
                    ->success()
                    ->send();
            });
    }
}
