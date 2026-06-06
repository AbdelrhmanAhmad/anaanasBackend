<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\EmailVerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    public function status(Request $request, EmailVerificationService $emailVerificationService)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'data' => $emailVerificationService->statusForUser($user),
        ]);
    }

    public function send(Request $request, EmailVerificationService $emailVerificationService)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        try {
            $emailVerificationService->sendCode($user);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('email_verification.code_sent'),
            'data' => $emailVerificationService->statusForUser($user->fresh()),
        ]);
    }

    public function verify(Request $request, EmailVerificationService $emailVerificationService)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{4,8}$/'],
        ]);

        try {
            $user = $emailVerificationService->verifyCode($user, $validated['code']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'message' => __('email_verification.error'),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('email_verification.verified'),
            'data' => [
                'user' => app(AuthController::class)->serializeUser($user),
                ...$emailVerificationService->statusForUser($user),
            ],
        ]);
    }

    public function changeEmail(Request $request, EmailVerificationService $emailVerificationService)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            $user = $emailVerificationService->requestEmailChange($user, $validated['email']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('email_verification.email_changed_code_sent'),
            'data' => $emailVerificationService->statusForUser($user),
        ]);
    }
}
