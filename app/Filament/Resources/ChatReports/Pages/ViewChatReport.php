<?php

namespace App\Filament\Resources\ChatReports\Pages;

use App\Filament\Resources\ChatReports\ChatReportResource;
use App\Models\Chat;
use App\Models\ChatReport;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\View\View;

/**
 * Custom admin view: shows the report details and the original conversation
 * end-to-end so a moderator can decide whether the complaint is justified.
 */
class ViewChatReport extends Page
{
    protected static string $resource = ChatReportResource::class;

    protected string $view = 'filament.resources.chat-reports.pages.view-chat-report';

    public ?ChatReport $record = null;
    public ?Chat $chat = null;
    public array $messages = [];
    public ?array $reporter = null;
    public ?array $reportedUser = null;
    public ?array $post = null;

    public function mount(string $record): void
    {
        $resolved = ChatReportResource::resolveRecordRouteBinding($record)
            ?? ChatReport::findByRouteKey($record);
        if ($resolved === null) {
            abort(404);
        }
        $this->record = $resolved;

        $this->chat = Chat::find($this->record->chat_id);

        if ($this->chat) {
            $this->messages = Message::where('chat_id', (string) $this->record->chat_id)
                ->orderBy('created_at', 'asc')
                ->limit(500)
                ->get()
                ->map(function ($m) {
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
                    ];
                })
                ->all();
        }

        $reporter = User::find((int) $this->record->reporter_id);
        $reportedUser = User::find((int) $this->record->reported_user_id);
        $post = Post::find((int) $this->record->post_id);

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
        ] : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mark_reviewed')
                ->label('تمييز كمراجَع')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->visible(fn () => $this->record?->status === ChatReport::STATUS_PENDING)
                ->action(fn () => $this->updateStatus(ChatReport::STATUS_REVIEWED, 'Marked as reviewed')),

            Action::make('dismiss')
                ->label('رفض البلاغ')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn () => $this->record?->status !== ChatReport::STATUS_DISMISSED)
                ->action(fn () => $this->updateStatus(ChatReport::STATUS_DISMISSED, 'Report dismissed')),

            Action::make('action_taken')
                ->label('اتخاذ إجراء وإغلاق المحادثة')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $this->record?->status !== ChatReport::STATUS_ACTION_TAKEN)
                ->action(function () {
                    if ($this->chat) {
                        $this->chat->close((int) auth()->id());
                    }
                    $this->updateStatus(ChatReport::STATUS_ACTION_TAKEN, 'Action taken — chat closed');
                }),
        ];
    }

    private function updateStatus(string $status, string $notice): void
    {
        $this->record->update([
            'status' => $status,
            'admin_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $this->record->refresh();

        Notification::make()
            ->title($notice)
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return 'بلاغ #' . ($this->record?->getKey() ?? '—');
    }
}
