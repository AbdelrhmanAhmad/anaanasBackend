<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthForgotPasswordRequest;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthRegisterRequest;
use App\Http\Requests\AuthResetPasswordRequest;
use App\Models\User;
use App\Rules\NoForbiddenWords;
use App\Models\UserNotification;
use App\Services\EmailVerificationService;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{

    public function register(AuthRegisterRequest $request)
    {
        $data = $request->validated();

        // Sanitize inputs (name and email are required by validation)
        $name = strip_tags($data['name']);
        $email = $data['email'];
        $mobile = !empty($data['mobile']) ? preg_replace('/[^0-9+\-\s]/', '', trim($data['mobile'])) : null;

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'password' => $data['password'], // hashed automatically via User cast
        ]);

        try {
            app(EmailVerificationService::class)->ensureInitialCode($user);
        } catch (\Throwable $e) {
            report($e);
        }

        Auth::login($user);
        $user = Auth::user();
        // إنشاء توكن لـ NextAuth / الواجهات
        $token = $user->createToken('anaanass-web')->plainTextToken;

        $displayName = trim((string) ($user->name ?? ''));
        $this->notifyUser($user, [
            'type' => 'auth.welcome',
            'title_ar' => 'مرحباً بك في أناناس!',
            'title_en' => 'Welcome to ANANAS!',
            'body_ar' => $displayName !== ''
                ? 'أهلاً ' . mb_substr($displayName, 0, 60) . '! سعدنا بانضمامك. ابدأ الآن باستكشاف المنصة وأضف أول إعلان لك.'
                : 'سعدنا بانضمامك. ابدأ الآن باستكشاف المنصة وأضف أول إعلان لك.',
            'body_en' => $displayName !== ''
                ? 'Hi ' . mb_substr($displayName, 0, 60) . '! Glad to have you on board. Start exploring and post your first listing.'
                : 'Glad to have you on board. Start exploring and post your first listing.',
            'url' => '/',
            'data' => $this->authNotificationContext($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data'    => [
                'user'  => $this->serializeUser($user),
                'token' => $token,
            ],
        ], 201);
    }

    public function login(AuthLoginRequest $request)
    {
        $data = $request->validated();

        // تحديد هل هنسجل بالبريد أو الموبايل بشكل آمن حتى لو أحد الحقول غير مُرسل
        $email = trim((string) ($data['email'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));
        $mobile = $mobile !== '' ? preg_replace('/\s+/', '', $mobile) : '';

        $field = $email !== '' ? 'email' : 'mobile';
        $value = $field === 'email' ? $email : $mobile;

        // check old sys login  useing old_system_password

        $user = User::where($field, $value)->first();
        if (!$user){
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 422);
        }else{
            $user->update([
                'try_login_in_new_system' =>true,
            ]) ;
            if ($user->old_system_password ){
                // old  login php smarty Sengen platform
                if (!password_verify($request->password, $user->old_system_password)) {
                    /* check brute-force attack detection  */
                    if (!Auth::attempt([$field => $value, 'password' => $data['password']])) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid credentials',
                        ], 422);
                    }
                }else{
                    $user->update([
                        'old_system_password' => null,
                        'password' => $data['password'],
                    ]) ;
                    Auth::login($user);
                }
            }else{

                if (!Auth::attempt([$field => $value, 'password' => $data['password']])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid credentials',
                    ], 422);
                }


            }
        }



        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->is_blocked) {
            Auth::logout();

            return response()->json([
                'success' => false,
                'message' => 'This account has been suspended.',
            ], 403);
        }

        // Cancel any pending account deletion request if user logs in within grace period
        $deletionRequest = \App\Models\AccountDeletionRequest::where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->first();

        if ($deletionRequest && $deletionRequest->canBeCancelled()) {
            $deletionRequest->update(['cancelled_at' => now()]);
        }

        // حذف التوكنات القديمة لو تحب
        // $user->tokens()->delete();

        $token = $user->createToken('anaanass-web')->plainTextToken;

        $displayName = trim((string) ($user->name ?? ''));
        $this->notifyUser($user, [
            'type' => 'auth.login',
            'title_ar' => $displayName !== ''
                ? 'أهلاً بعودتك، ' . mb_substr($displayName, 0, 60) . '!'
                : 'أهلاً بعودتك!',
            'title_en' => $displayName !== ''
                ? 'Welcome back, ' . mb_substr($displayName, 0, 60) . '!'
                : 'Welcome back!',
            'body_ar' => 'تم تسجيل دخولك بنجاح. إن لم تكن أنت، يُرجى تغيير كلمة المرور فوراً.',
            'body_en' => 'You signed in successfully. If this wasn’t you, please change your password immediately.',
            'url' => '/',
            'data' => $this->authNotificationContext($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully',
            'data'    => [
                'user'  => $this->serializeUser($user),
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'user' => $this->userToArrayWithMedia($user),
            ],
        ]);
    }

    /**
     * Persist an in-app notification for the user.
     * Best-effort: never throws — auth flows must not fail because of a notification write.
     */
    protected function notifyUser(?User $user, array $payload): void
    {
        if (!$user || !$user->id) {
            return;
        }
        try {
            UserNotification::create(array_merge([
                'user_id' => (int) $user->id,
                'is_read' => false,
            ], $payload));
        } catch (\Throwable $e) {
            // swallow — notifications are non-critical for auth flows
        }
    }

    /**
     * Build a request fingerprint (IP + UA) we can attach to security-sensitive
     * notifications so users can audit suspicious activity later.
     */
    protected function authNotificationContext(Request $request): array
    {
        $ua = (string) $request->header('User-Agent', '');
        return [
            'ip' => (string) $request->ip(),
            'user_agent' => $ua !== '' ? mb_substr($ua, 0, 255) : null,
            'at' => now()->toIso8601String(),
        ];
    }

    /**
     * Resolve a stored media path to a public URL.
     * - Full URLs (http/https) are returned as-is.
     * - Paths stored via the new S3 flow (e.g. "upload/photos/...") resolve through the S3 disk.
     * - Legacy paths written via the `public` disk still resolve through the public disk.
     */
    protected function resolveMediaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        try {
            if (str_starts_with($path, 'upload/') || str_starts_with($path, 'avatars/') || str_starts_with($path, 'covers/')) {
                // Files written with the new S3 flow (public-visibility object, path-only stored in DB)
                return Storage::disk('s3')->url($path);
            }
            // Fallback for legacy entries stored via the "public" disk
            return Storage::disk('public')->url($path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Build a serializable user payload, always including avatar_url/cover_image_url
     * so the frontend can render images from S3 consistently.
     */
    protected function userToArrayWithMedia(User $user): array
    {
        $userData = $user->toArray();
        $userData['avatar_url'] = $this->resolveMediaUrl($user->avatar);
        $userData['cover_image_url'] = $this->resolveMediaUrl($user->cover_image);
        $userData['email_verified'] = $user->hasVerifiedEmail();
        $userData['pending_email'] = $user->pending_email;
        $userData['verification_email'] = $user->pending_email ?: $user->email;

        return $userData;
    }

    public function serializeUser(User $user): array
    {
        return $this->userToArrayWithMedia($user);
    }

    /**
     * Upload a profile media file (avatar or cover) to S3 and return the stored path.
     * Mirrors the pattern used by PostController for post images.
     */
    protected function uploadProfileMediaToS3(\Illuminate\Http\UploadedFile $file, string $userId, string $kind): ?string
    {
        if (!$file->isValid()) {
            return null;
        }

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg';
        $prefix = ($kind === 'avatar' ? 'avatar_' : 'cover_') . Str::random(12);
        $path = 'upload/profiles/' . date('Y') . '/' . date('m') . '/' . $userId . '/' . $prefix . '.' . $extension;

        Storage::disk('s3')->put(
            $path,
            fopen($file->getRealPath(), 'r'),
            [
                'visibility' => 'public',
                'ContentType' => $file->getMimeType(),
                'ContentDisposition' => 'inline',
            ]
        );

        return $path;
    }

    /**
     * Best-effort cleanup of the previous media file to avoid orphan objects in S3.
     * Skips legacy public-disk paths (older entries) to avoid accidental local deletions.
     */
    protected function deleteProfileMediaIfS3(?string $path): void
    {
        if (!$path) {
            return;
        }
        if (preg_match('/^https?:\/\//i', $path)) {
            return;
        }
        try {
            if (str_starts_with($path, 'upload/') || str_starts_with($path, 'avatars/') || str_starts_with($path, 'covers/')) {
                Storage::disk('s3')->delete($path);
            }
        } catch (\Throwable $e) {
            // swallow — cleanup is best-effort
        }
    }

    /**
     * Request password reset link
     */
    public function forgotPassword(AuthForgotPasswordRequest $request)
    {
        $data = $request->validated();
        $status = Password::sendResetLink($data);

        if ($status !== Password::RESET_LINK_SENT) {
            $message = match ($status) {
                Password::INVALID_USER => __('passwords.user'),
                Password::RESET_THROTTLED => __('passwords.throttled'),
                default => __('passwords.user'),
            };
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        // Notify only after a real reset link was dispatched.
        $resetUser = User::where('email', $data['email'])->first();
        $this->notifyUser($resetUser, [
            'type' => 'auth.password_reset_requested',
            'title_ar' => 'طلب إعادة تعيين كلمة المرور',
            'title_en' => 'Password reset requested',
            'body_ar' => 'استلمنا طلب إعادة تعيين كلمة المرور لحسابك وأرسلنا الرابط إلى بريدك الإلكتروني. إن لم تكن أنت، تجاهل الرسالة وأمّن حسابك.',
            'body_en' => 'We received a request to reset your password and sent the link to your email. If this wasn’t you, ignore it and secure your account.',
            'url' => '/auth/forgot-pass',
            'data' => $this->authNotificationContext($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('passwords.sent'),
        ]);
    }

    /**
     * Reset password with token
     */
    public function resetPassword(AuthResetPasswordRequest $request)
    {
        $data = $request->validated();
        $resetUser = null;
        $status = Password::reset($data, function ($user, $password) use (&$resetUser) {
            $user->forceFill([
                'password' => \Hash::make($password),
            ])->save();
            $resetUser = $user;
        });

        if ($status !== Password::PASSWORD_RESET) {
            $message = match ($status) {
                Password::INVALID_TOKEN => __('passwords.token'),
                Password::INVALID_USER => __('passwords.user'),
                Password::RESET_THROTTLED => __('passwords.throttled'),
                default => __('passwords.token'),
            };
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        // Fallback: re-fetch the user model in case the broker passed a base contract.
        if (!($resetUser instanceof User)) {
            $resetUser = User::where('email', $data['email'])->first();
        }

        $this->notifyUser($resetUser, [
            'type' => 'auth.password_changed',
            'title_ar' => 'تم تغيير كلمة المرور بنجاح',
            'title_en' => 'Password changed successfully',
            'body_ar' => 'تم تحديث كلمة المرور لحسابك. إن لم تكن أنت من قام بهذا الإجراء، يُرجى التواصل مع الدعم فوراً.',
            'body_en' => 'Your account password has been updated. If this wasn’t you, please contact support immediately.',
            'url' => '/auth/sign-in',
            'data' => $this->authNotificationContext($request),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('passwords.reset'),
        ]);
    }

    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Persist the logout notification BEFORE revoking the token
        // so the next session picks it up in the bell.
        $this->notifyUser($user, [
            'type' => 'auth.logout',
            'title_ar' => 'تم تسجيل الخروج',
            'title_en' => 'Logged out',
            'body_ar' => 'تم تسجيل خروجك بنجاح. أراك قريباً!',
            'body_en' => 'You have been logged out successfully. See you soon!',
            'url' => '/auth/sign-in',
            'data' => $this->authNotificationContext($request),
        ]);

        // حذف التوكن الحالي فقط
        $user->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $forbiddenRule = new NoForbiddenWords();

        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255', $forbiddenRule],
            'last_name' => ['sometimes', 'string', 'max:255', $forbiddenRule],
            'username' => ['sometimes', 'string', 'max:255', 'unique:users,username,' . $user->id, $forbiddenRule],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'mobile' => ['sometimes', 'string', 'max:20'],
            'bio' => ['sometimes', 'string', 'max:300', $forbiddenRule],
            'date_of_birth' => 'sometimes|date',
            'avatar' => 'sometimes|string|max:500',
            'cover_image' => 'sometimes|string|max:500',
            'avatar_file' => 'sometimes|file|mimes:jpg,jpeg,png,webp|max:5120',
            'cover_image_file' => 'sometimes|file|mimes:jpg,jpeg,png,webp|max:5120',
            'allow_team_invites' => 'sometimes|boolean',
        ]);

        // Handle avatar file upload (stored on S3)
        if ($request->hasFile('avatar_file')) {
            $newAvatarPath = $this->uploadProfileMediaToS3(
                $request->file('avatar_file'),
                (string) $user->id,
                'avatar'
            );
            if ($newAvatarPath) {
                $this->deleteProfileMediaIfS3($user->avatar);
                $validated['avatar'] = $newAvatarPath;
            }
        }

        // Handle cover image file upload (stored on S3)
        if ($request->hasFile('cover_image_file')) {
            $newCoverPath = $this->uploadProfileMediaToS3(
                $request->file('cover_image_file'),
                (string) $user->id,
                'cover'
            );
            if ($newCoverPath) {
                $this->deleteProfileMediaIfS3($user->cover_image);
                $validated['cover_image'] = $newCoverPath;
            }
        }

        // Update name if first_name or last_name provided
        if (isset($validated['first_name']) || isset($validated['last_name'])) {
            $nameParts = [];
            if (isset($validated['first_name'])) {
                $nameParts[] = $validated['first_name'];
            }
            if (isset($validated['last_name'])) {
                $nameParts[] = $validated['last_name'];
            }
            if (!empty($nameParts)) {
                $validated['name'] = implode(' ', $nameParts);
            }
        }

        // Remove file fields from validated (they're already processed)
        unset($validated['avatar_file'], $validated['cover_image_file']);

        $emailVerificationService = app(EmailVerificationService::class);
        $emailChangeRequested = false;

        if (isset($validated['email'])) {
            $newEmail = mb_strtolower(trim((string) $validated['email']));
            $currentEmail = mb_strtolower(trim((string) $user->email));

            if ($newEmail !== $currentEmail) {
                try {
                    $emailVerificationService->requestEmailChange($user, $newEmail);
                    $emailChangeRequested = true;
                } catch (ValidationException $e) {
                    return response()->json([
                        'success' => false,
                        'message' => collect($e->errors())->flatten()->first(),
                        'errors' => $e->errors(),
                    ], 422);
                }
            }

            unset($validated['email']);
        }

        if (! empty($validated)) {
            $user->update($validated);
        }

        // Refresh user to get updated data and include resolved media URLs
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => $emailChangeRequested
                ? __('email_verification.email_changed_code_sent')
                : 'Profile updated successfully',
            'data' => [
                'user' => $this->userToArrayWithMedia($user),
            ],
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        // Verify current password
        if (!\Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 422);
        }

        // Update password
        $user->update([
            'password' => \Hash::make($validated['new_password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    /**
     * Request account deletion
     */
    public function requestAccountDeletion(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Check if there's already an active deletion request
        $existingRequest = \App\Models\AccountDeletionRequest::where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending account deletion request',
            ], 422);
        }

        // Create deletion request
        $deletionRequest = \App\Models\AccountDeletionRequest::create([
            'user_id' => $user->id,
            'requested_at' => now(),
            'scheduled_deletion_at' => now()->addDays(30),
        ]);

        // Logout user (revoke all tokens)
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deletion requested. You have been logged out. Your account will be deleted in 30 days if you do not log in.',
            'data' => [
                'scheduled_deletion_at' => $deletionRequest->scheduled_deletion_at->toISOString(),
            ],
        ]);
    }

    /**
     * Cancel account deletion request (if user logs in within 30 days)
     */
    public function cancelAccountDeletion(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $deletionRequest = \App\Models\AccountDeletionRequest::where('user_id', $user->id)
            ->whereNull('cancelled_at')
            ->whereNull('deleted_at')
            ->first();

        if (!$deletionRequest) {
            return response()->json([
                'success' => false,
                'message' => 'No active deletion request found',
            ], 404);
        }

        if (!$deletionRequest->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot cancel deletion request. The grace period has expired.',
            ], 422);
        }

        $deletionRequest->update([
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account deletion request has been cancelled',
        ]);
    }
}
