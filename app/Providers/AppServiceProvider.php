<?php

namespace App\Providers;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('contact', function (Request $request) {
            $ip = (string) $request->ip();
            $perMinute = max(1, (int) config('contact.rate_limit.per_minute', 2));
            $perHour = max(1, (int) config('contact.rate_limit.per_hour', 8));
            $authPerHour = max(1, (int) config('contact.rate_limit.auth_per_hour', 5));

            $tooManyResponse = function (Request $request, array $headers) {
                return response()->json([
                    'success' => false,
                    'message' => __('contact.rate_limited'),
                ], 429, $headers);
            };

            $limits = [
                Limit::perMinute($perMinute)
                    ->by('contact:ip:'.$ip)
                    ->response($tooManyResponse),
                Limit::perHour($perHour)
                    ->by('contact:ip-hour:'.$ip)
                    ->response($tooManyResponse),
            ];

            $user = $request->user();
            if ($user?->id) {
                $limits[] = Limit::perHour($authPerHour)
                    ->by('contact:user:'.(int) $user->id)
                    ->response($tooManyResponse);
            }

            return $limits;
        });

        RateLimiter::for('email-verify-send', function (Request $request) {
            $userId = (int) ($request->user()?->id ?? 0);
            $perHour = max(10, (int) config('email_verification.resend_per_hour', 666));

            return Limit::perHour($perHour)
                ->by('email-verify-send:'.$userId)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => __('email_verification.send_rate_limited'),
                    ], 429, $headers);
                });
        });

        RateLimiter::for('email-verify-attempt', function (Request $request) {
            $userId = (int) ($request->user()?->id ?? 0);
            $perHour = max(1, (int) config('email_verification.verify_per_hour', 20));

            return Limit::perHour($perHour)
                ->by('email-verify-attempt:'.$userId)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => __('email_verification.verify_rate_limited'),
                    ], 429, $headers);
                });
        });

        TranslatableTabs::configureUsing(function (TranslatableTabs $component) {
            $component
                // locales labels
                ->localesLabels([
                    'ar' => "اللغه العربيه",
                    'en' => "اللغه الانجليزيه"
                ])
                // default locales
                ->locales(['ar', 'en']);
        });
    }
}
