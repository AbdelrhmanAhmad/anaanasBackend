<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\AccountVerificationRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\AccountVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountVerificationController extends Controller
{
    public function status(Request $request, AccountVerificationService $verificationService)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $verificationService->statusForUser((int) $user->id),
        ]);
    }

    public function store(Request $request, AccountVerificationService $verificationService)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ($verificationService->isVerified($user)) {
            return response()->json([
                'success' => false,
                'message' => __('account_verification.already_verified'),
            ], 422);
        }

        $hasPending = AccountVerificationRequest::query()
            ->where('user_id', $user->id)
            ->where('status', AccountVerificationRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return response()->json([
                'success' => false,
                'message' => __('account_verification.request_pending'),
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:1000'],
        ]);

        AccountVerificationRequest::query()->create([
            'user_id' => (int) $user->id,
            'message' => $validated['message'] ?? null,
            'status' => AccountVerificationRequest::STATUS_PENDING,
        ]);

        try {
            UserNotification::query()->create([
                'user_id' => (int) $user->id,
                'type' => 'account_verification_submitted',
                'title_ar' => 'تم إرسال طلب التوثيق',
                'title_en' => 'Verification request submitted',
                'body_ar' => 'استلمنا طلب توثيق حسابك. سنراجعه ونُبلغك عند اتخاذ القرار.',
                'body_en' => 'We received your account verification request. We will review it and notify you.',
                'is_read' => false,
            ]);
        } catch (\Throwable) {
            // non-critical
        }

        return response()->json([
            'success' => true,
            'message' => __('account_verification.request_sent'),
            'data' => $verificationService->statusForUser((int) $user->id),
        ], 201);
    }
}
