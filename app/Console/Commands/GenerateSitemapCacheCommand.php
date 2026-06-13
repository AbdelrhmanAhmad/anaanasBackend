<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\v1\SitemapController;
use App\Models\Country;
use App\Support\SitemapStorage;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class GenerateSitemapCacheCommand extends Command
{
    protected $signature = 'sitemap:generate {--country= : Limit to one ISO2 country code}';

    protected $description = 'Warm sitemap JSON caches for each country subdomain (daily SEO job).';

    public function handle(SitemapController $controller): int
    {
        $onlyIso = strtolower(trim((string) $this->option('country')));

        $countries = Country::query()
            ->where(function ($q) {
                $q->whereNotNull('iso2')->orWhereNotNull('iso_code');
            })
            ->when($onlyIso !== '', function ($q) use ($onlyIso) {
                $q->where(function ($sub) use ($onlyIso) {
                    $sub->whereRaw('LOWER(iso2) = ?', [$onlyIso])
                        ->orWhereRaw('LOWER(iso_code) = ?', [$onlyIso]);
                });
            })
            ->orderBy('id')
            ->get();

        if ($countries->isEmpty()) {
            $this->warn('No countries found for sitemap generation.');

            return self::FAILURE;
        }

        $disk = SitemapStorage::disk();
        $root = storage_path('app/sitemap-cache');
        $this->info("Writing sitemap cache to: {$root}");

        foreach ($countries as $country) {
            $iso2 = strtolower((string) ($country->iso2 ?: $country->iso_code));
            if ($iso2 === '') {
                continue;
            }

            $this->info("Generating sitemap cache for {$iso2}…");
            $request = Request::create('/', 'GET', ['country_iso2' => $iso2]);

            foreach (['sections', 'cities'] as $type) {
                $response = match ($type) {
                    'sections' => $controller->sections($request),
                    'cities' => $controller->cities($request),
                    default => null,
                };

                $decoded = json_decode($response->getContent(), true);
                $count = is_array($decoded['data'] ?? null) ? count($decoded['data']) : 0;

                $disk->put(
                    "{$iso2}/{$type}.json",
                    $response->getContent()
                );

                $this->line("  - {$type}: {$count} entries");
            }

            $page = 1;
            $totalPosts = 0;
            do {
                $postsRequest = Request::create('/', 'GET', [
                    'country_iso2' => $iso2,
                    'page' => $page,
                    'per_page' => 1000,
                ]);
                $postsResponse = $controller->posts($postsRequest);
                $decoded = json_decode($postsResponse->getContent(), true);
                $pageCount = is_array($decoded['data'] ?? null) ? count($decoded['data']) : 0;
                $totalPosts = (int) ($decoded['meta']['total'] ?? $totalPosts);

                $disk->put(
                    "{$iso2}/posts-page-{$page}.json",
                    $postsResponse->getContent()
                );

                $lastPage = (int) ($decoded['meta']['last_page'] ?? 1);
                $page++;
            } while ($page <= $lastPage);

            $this->line("  - posts: {$totalPosts} total");
        }

        $countriesResponse = $controller->countries();
        $disk->put('countries.json', $countriesResponse->getContent());

        $this->info('Sitemap cache generation complete.');

        return self::SUCCESS;
    }
}
