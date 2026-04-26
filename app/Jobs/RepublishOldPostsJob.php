<?php

namespace App\Jobs;

use App\Models\Country;
use App\Models\Post;

class RepublishOldPostsJob
{
    /**
     * Re-publish the oldest 3 posts per country by bumping publish_date.
     * IDs and created_at stay unchanged.
     */
    public function handle(): void
    {
        $now = now()->tz("Asia/Amman");

        Country::query()
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($countryId) use ($now) {
                $postIds = Post::query()
                    ->where('country_id', (int) $countryId)
                    ->where(function ($q) {
                        $q->whereNull('post_type')->orWhere('post_type', 'listing');
                    })
                    ->orderByRaw('COALESCE(publish_date, created_at) asc')
                    ->limit(3)
                    ->pluck('id');

                if ($postIds->isEmpty()) {
                    return;
                }

                Post::query()
                    ->whereIn('id', $postIds->all())
                    ->update([
                        'publish_date' => $now,
                    ]);
            });
    }
}
