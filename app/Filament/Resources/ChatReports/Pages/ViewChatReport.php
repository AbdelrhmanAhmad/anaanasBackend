<?php

namespace App\Filament\Resources\ChatReports\Pages;

use App\Filament\Resources\ChatReports\ChatReportResource;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Chat;
use App\Models\ChatReport;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Custom admin view: report metadata + full conversation for moderators.
 */
class ViewChatReport extends ViewRecord
{
    protected static string $resource = ChatReportResource::class;

    protected string $view = 'filament.resources.chat-reports.pages.view-chat-report';

    public ?Chat $chat = null;

    public array $messages = [];

    public ?array $reporter = null;

    public ?array $reportedUser = null;

    public ?array $post = null;

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);

        static::authorizeResourceAccess();

        $this->loadConversationContext();
    }

    protected function loadConversationContext(): void
    {
        /** @var ChatReport $report */
        $report = $this->getRecord();

        $this->chat = Chat::find($report->chat_id);

        if ($this->chat) {
            $this->messages = Message::where('chat_id', (string) $report->chat_id)
                ->orderBy('created_at', 'asc')
                ->limit(500)
                ->get()
                ->map(function ($m) use ($report) {
                    $sender = User::find($m->sender_id);

                    return [
                        'id' => (string) ($m->_id ?? $m->id),
                        'sender_id' => (int) $m->sender_id,
                        'sender_name' => $sender?->name ?? '—',
                        'sender_avatar' => $sender?->avatar_url,
                        'body' => (string) $m->body,
                        'type' => $m->type ?? 'text',
                        'file_url' => $m->file_url,
                        'created_at' => $m->created_at?->toDateTimeString(),
                        'is_reporter' => (int) $m->sender_id === (int) $report->reporter_id,
                    ];
                })
                ->all();
        }

        $reporter = User::find((int) $report->reporter_id);
        $reportedUser = User::find((int) $report->reported_user_id);
        $post = Post::find((int) $report->post_id);

        $this->reporter = $reporter ? [
            'id' => $reporter->id,
            'name' => $reporter->name,
            'email' => $reporter->email,
            'avatar' => $reporter->avatar_url,
        ] : null;

        $this->reportedUser = $reportedUser ? [
            'id' => $reportedUser->id,
            'name' => $reportedUser->name,
            'email' => $reportedUser->email,
            'avatar' => $reportedUser->avatar_url,
        ] : null;

        $this->post = $post ? [
            'id' => $post->id,
            'title' => $post->title,
            'admin_url' => PostResource::getUrl('view', ['record' => $post->id]),
        ] : null;
    }

    public function statusLabel(?string $status = null): string
    {
        $status ??= (string) ($this->getRecord()->status ?? '');

        return match ($status) {
            ChatReport::STATUS_PENDING => 'قيد الانتظار',
            ChatReport::STATUS_REVIEWED => 'تمّت المراجعة',
            ChatReport::STATUS_DISMISSED => 'مرفوض',
            ChatReport::STATUS_ACTION_TAKEN => 'إجراء متّخذ',
            default => $status !== '' ? $status : '—',
        };
    }

    public function categoryLabel(?string $category = null): string
    {
        $category ??= (string) ($this->getRecord()->category ?? '');

        return match ($category) {
            'spam' => 'رسائل مزعجة',
            'harassment' => 'تحرش / إساءة',
            'scam' => 'احتيال',
            'inappropriate' => 'محتوى غير لائق',
            'other' => 'أخرى',
            default => $category !== '' ? $category : '—',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_reviewed')
                ->label('تمييز كمراجَع')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->visible(fn () => $this->getRecord()->status === ChatReport::STATUS_PENDING)
                ->action(fn () => $this->updateStatus(ChatReport::STATUS_REVIEWED, 'تم تمييز البلاغ كمراجَع')),

            Action::make('dismiss')
                ->label('رفض البلاغ')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->getRecord()->status !== ChatReport::STATUS_DISMISSED)
                ->action(fn () => $this->updateStatus(ChatReport::STATUS_DISMISSED, 'تم رفض البلاغ')),

            Action::make('action_taken')
                ->label('اتخاذ إجراء وإغلاق المحادثة')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->getRecord()->status !== ChatReport::STATUS_ACTION_TAKEN)
                ->action(function () {
                    if ($this->chat) {
                        $this->chat->close((int) auth()->id());
                    }
                    $this->updateStatus(ChatReport::STATUS_ACTION_TAKEN, 'تم اتخاذ إجراء وإغلاق المحادثة');
                }),
        ];
    }

    private function updateStatus(string $status, string $notice): void
    {
        $this->getRecord()->update([
            'status' => $status,
            'admin_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $this->record = $this->getRecord()->fresh();

        Notification::make()
            ->title($notice)
            ->success()
            ->send();
    }

    public function getTitle(): string | Htmlable
    {
        return 'بلاغ #' . ($this->getRecord()->getKey() ?? '—');
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'مراجعة تفاصيل البلاغ ومحتوى المحادثة';
    }
}
