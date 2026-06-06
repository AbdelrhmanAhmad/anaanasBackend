<?php

namespace App\Http\Middleware;

// use Auth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('lang', config('app.locale'));

        // Optional Sanctum auth on public API routes (e.g. owner preview of pending posts).
        if ($request->bearerToken()) {
            $user = Auth::guard('sanctum')->user();
            if (! $user) {
                $accessToken = PersonalAccessToken::findToken($request->bearerToken());
                $user = $accessToken?->tokenable;
            }
            if ($user) {
                Auth::setUser($user);
            }
        }


        // Validate that the locale is supported
        if (in_array($locale, config('app.supported_locales', ['en']))) {
            app()->setLocale($locale);
        } else {
            app()->setLocale(config('app.fallback_locale', 'en'));
        }

        return $next($request);
    }
}
