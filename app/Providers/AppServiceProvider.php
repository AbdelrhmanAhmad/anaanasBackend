<?php

namespace App\Providers;

use AbdulmajeedJamaan\FilamentTranslatableTabs\TranslatableTabs;
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
