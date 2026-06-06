<?php

namespace App\Services;

use App\Mail\NewContactRequestMail;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactRequestService
{
    public function __construct(
        protected PlatformSettingsService $platformSettings,
    ) {}

    public function submit(Request $request, ?User $user, array $validated): ContactRequest
    {
        $contactRequest = ContactRequest::query()->create([
            'user_id' => $user?->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => ContactRequest::STATUS_PENDING,
            'ip' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->header('User-Agent', ''), 0, 255) ?: null,
        ]);

        $this->notifyAdmins($contactRequest);

        return $contactRequest;
    }

    public function updateStatus(
        ContactRequest $record,
        string $status,
        ?int $adminId,
        ?string $adminNotes = null,
    ): void {
        if (! in_array($status, ContactRequest::STATUSES, true)) {
            return;
        }

        $record->update([
            'status' => $status,
            'handled_by' => $adminId,
            'handled_at' => now(),
            'admin_notes' => $adminNotes ?? $record->admin_notes,
        ]);
    }

    protected function notifyAdmins(ContactRequest $contactRequest): void
    {
        $emails = $this->platformSettings->contactNotificationEmails();
        if ($emails === []) {
            return;
        }

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new NewContactRequestMail($contactRequest));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
