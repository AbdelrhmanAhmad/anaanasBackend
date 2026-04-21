<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthForgotPasswordRequest;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Requests\AuthRegisterRequest;
use App\Http\Requests\AuthResetPasswordRequest;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        Auth::login($user);
        $user = Auth::user();
        // إنشاء توكن لـ NextAuth / الواجهات
        $token = $user->createToken('anaanass-web')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data'    => [
                'user'  => $user,
                'token' => $token,
            ],
        ], 201);
    }

    public function login(AuthLoginRequest $request)
    {
        $data = $request->validated();

        // تحديد هل هنسجل بالبريد أو الموبايل
        $field = $data['email'] ? 'email' : 'mobile';
        $value = $data[$field];

        if (!Auth::attempt([$field => $value, 'password' => $data['password']])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 422);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

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

        return response()->json([
            'success' => true,
            'message' => 'User logged in successfully',
            'data'    => [
                'user'  => $user,
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
        return $userData;
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
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill([
                'password' => \Hash::make($password),
            ])->save();
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

        return response()->json([
            'success' => true,
            'message' => __('passwords.reset'),
        ]);
    }

    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

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

        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:users,username,' . $user->id,
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'mobile' => 'sometimes|string|max:20',
            'bio' => 'sometimes|string|max:300',
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

        $user->update($validated);

        // Refresh user to get updated data and include resolved media URLs
        $user->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
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
