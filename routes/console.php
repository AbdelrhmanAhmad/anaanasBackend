<?php

use App\Jobs\RepublishOldPostsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule account deletion command to run daily
Schedule::command('accounts:delete-expired')->daily();

// Re-publish oldest listings per country to keep feed fresh.
Schedule::call(fn () => app(RepublishOldPostsJob::class)->handle())->everyFifteenMinutes();


