<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && ! $user->email_verified_at) {
            return response()->json([
                'success' => false,
                'error_code' => 'email_not_verified',
                'message' => __('email_verification.required'),
            ], 403);
        }

        return $next($request);
    }
}
