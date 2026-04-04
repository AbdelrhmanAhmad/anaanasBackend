<?php

namespace App\Http\Middleware;

// use Auth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        //  $locale = $request->lang ?? config('app.locale');

        if ($request->bearerToken()) {
            $user = Auth::guard('sanctum')->user();
            if (isset($user)) {
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
