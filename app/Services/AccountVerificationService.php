<?php

namespace App\Services;

use App\Models\AccountVerificationRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

class AccountVerificationService
{
    public function isVerified(User|int|null $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (is_int($user)) {
            $user = User::query()->find($user);
        }

        return (bool) ($user?->is_account_verified);
    }

    /**
     * @return 'none'|'pending'|'approved'|'rejected'
     */
    public function latestRequestStatus(int $userId): string
    {
        $latest = AccountVerificationRequest::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->first(['status']);

        if (! $latest) {
            return 'none';
        }

        return (string) $latest->status;
    }

    /**
     * @return array{
     *     is_account_verified: bool,
     *     verification_request_status: 'none'|'pending'|'approved'|'rejected',
     * }
     */
    public function statusForUser(int $userId): array
    {
        $user = User::query()->find($userId);

        return [
            'is_account_verified' => $this->isVerified($user),
            'verification_request_status' => $this->isVerified($user)
                ? 'approved'
                : $this->latestRequestStatus($userId),
        ];
    }

    public function approve(AccountVerificationRequest $request, int $adminId, ?string $adminNotes = null): void
    {
        DB::transaction(function () use ($request, $adminId, $adminNotes) {
            $request->update([
                'status' => AccountVerificationRequest::STATUS_APPROVED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'admin_notes' => $adminNotes,
            ]);

            $user = $request->user;
            if ($user) {
                $user->update([
                    'is_account_verified' => true,
                    'account_verified_at' => now(),
                ]);

                $this->notifyUserDecision($user, approved: true);
            }
        });
    }

    public function reject(AccountVerificationRequest $request, int $adminId, ?string $adminNotes = null): void
    {
        DB::transaction(function () use ($request, $adminId, $adminNotes) {
            $request->update([
                'status' => AccountVerificationRequest::STATUS_REJECTED,
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'admin_notes' => $adminNotes,
            ]);

            if ($request->user) {
                $this->notifyUserDecision($request->user, approved: false);
            }
        });
    }

    protected function notifyUserDecision(User $user, bool $approved): void
    {
        try {
            UserNotification::query()->create([
                'user_id' => (int) $user->id,
                'type' => $approved ? 'account_verification_approved' : 'account_verification_rejected',
                'title_ar' => $approved
                    ? __('account_verification.approved_title', locale: 'ar')
                    : __('account_verification.rejected_title', locale: 'ar'),
                'title_en' => $approved
                    ? __('account_verification.approved_title', locale: 'en')
                    : __('account_verification.rejected_title', locale: 'en'),
                'body_ar' => $approved
                    ? __('account_verification.approved_body', locale: 'ar')
                    : __('account_verification.rejected_body', locale: 'ar'),
                'body_en' => $approved
                    ? __('account_verification.approved_body', locale: 'en')
                    : __('account_verification.rejected_body', locale: 'en'),
                'is_read' => false,
            ]);
        } catch (\Throwable) {
            // non-critical
        }
    }
}
