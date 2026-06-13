<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Sitemap JSON cache — uses Storage::build() so it works even when
 * config is cached before the "sitemap" disk was added to filesystems.php.
 */
final class SitemapStorage
{
    public static function disk(): Filesystem
    {
        return Storage::build([
            'driver' => 'local',
            'root' => storage_path('app/sitemap-cache'),
            'throw' => false,
        ]);
    }
}
