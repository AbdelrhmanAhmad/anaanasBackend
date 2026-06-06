<?php

namespace App\Services;

use App\Mail\VerifyEmailCodeMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function hasVerifiedEmail(User $user): bool
    {
        return $user->email_verified_at !== null;
    }

    public function targetEmail(User $user): ?string
    {
        return $user->pending_email ?: $user->email;
    }

    /**
     * @return array{
     *     email_verified: bool,
     *     email: string|null,
     *     pending_email: string|null,
     *     verification_email: string|null,
     *     can_resend: bool,
     *     code_expires_at: string|null,
     * }
     */
    public function statusForUser(User $user): array
    {
        $activeCode = $this->latestActiveCode($user);

        return [
            'email_verified' => $this->hasVerifiedEmail($user),
            'email' => $user->email,
            'pending_email' => $user->pending_email,
            'verification_email' => $this->targetEmail($user),
            'can_resend' => true,
            'code_expires_at' => $activeCode?->expires_at?->toIso8601String(),
        ];
    }

    public function sendCode(User $user): void
    {
        if ($this->hasVerifiedEmail($user)) {
            throw ValidationException::withMessages([
                'email' => [__('email_verification.already_verified')],
            ]);
        }

        $email = $this->targetEmail($user);
        if (! $email) {
            throw ValidationException::withMessages([
                'email' => [__('email_verification.missing_email')],
            ]);
        }

        $this->invalidateCodesForUser((int) $user->id);

        $plainCode = $this->generateCode();
        $expiresMinutes = max(1, (int) config('email_verification.expires_minutes', 15));

        EmailVerificationCode::query()->create([
            'user_id' => (int) $user->id,
            'email' => $email,
            'code_hash' => Hash::make($plainCode),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($expiresMinutes),
        ]);

        try {
            Mail::to($email)->send(new VerifyEmailCodeMail($user, $plainCode, $expiresMinutes));
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages([
                'email' => [__('email_verification.send_failed')],
            ]);
        }
    }

    public function verifyCode(User $user, string $code): User
    {
        if ($this->hasVerifiedEmail($user)) {
            throw ValidationException::withMessages([
                'code' => [__('email_verification.already_verified')],
            ]);
        }

        $record = $this->latestActiveCode($user);
        if (! $record) {
            throw ValidationException::withMessages([
                'code' => [__('email_verification.code_expired')],
            ]);
        }

        $maxAttempts = max(1, (int) config('email_verification.max_attempts', 5));
        if ($record->attempts >= $maxAttempts) {
            $this->invalidateCodesForUser((int) $user->id);
            throw ValidationException::withMessages([
                'code' => [__('email_verification.too_many_attempts')],
            ]);
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');
            throw ValidationException::withMessages([
                'code' => [__('email_verification.invalid_code')],
            ]);
        }

        $updates = [
            'email_verified_at' => now(),
        ];

        if ($user->pending_email) {
            $updates['email'] = $user->pending_email;
            $updates['pending_email'] = null;
        }

        $user->update($updates);
        $this->invalidateCodesForUser((int) $user->id);

        return $user->fresh();
    }

    public function requestEmailChange(User $user, string $email): User
    {
        $email = mb_strtolower(trim($email));

        if ($this->hasVerifiedEmail($user) && mb_strtolower((string) $user->email) === $email) {
            throw ValidationException::withMessages([
                'email' => [__('email_verification.same_email')],
            ]);
        }

        $exists = User::query()
            ->where('id', '!=', (int) $user->id)
            ->where(function ($query) use ($email) {
                $query->where('email', $email)->orWhere('pending_email', $email);
            })
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'email' => [__('email_verification.email_taken')],
            ]);
        }

        $user->update([
            'pending_email' => $email,
            'email_verified_at' => null,
        ]);

        $user = $user->fresh();
        $this->sendCode($user);

        return $user;
    }

    public function ensureInitialCode(User $user): void
    {
        if ($this->hasVerifiedEmail($user)) {
            return;
        }

        if ($this->latestActiveCode($user)) {
            return;
        }

        $this->sendCode($user);
    }

    protected function latestActiveCode(User $user): ?EmailVerificationCode
    {
        return EmailVerificationCode::query()
            ->where('user_id', (int) $user->id)
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    protected function invalidateCodesForUser(int $userId): void
    {
        EmailVerificationCode::query()
            ->where('user_id', $userId)
            ->delete();
    }

    protected function generateCode(): string
    {
        $length = max(4, (int) config('email_verification.code_length', 6));

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
