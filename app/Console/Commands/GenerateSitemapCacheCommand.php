<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\v1\SitemapController;
use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GenerateSitemapCacheCommand extends Command
{
    protected $signature = 'sitemap:generate {--country= : Limit to one ISO2 country code}';

    protected $description = 'Warm sitemap JSON caches for each country subdomain (daily SEO job).';

    public function handle(SitemapController $controller): int
    {
        $onlyIso = strtolower(trim((string) $this->option('country')));

        $countries = Country::query()
            ->whereNotNull('iso2')
            ->when($onlyIso !== '', function ($q) use ($onlyIso) {
                $q->whereRaw('LOWER(iso2) = ?', [$onlyIso])
                    ->orWhereRaw('LOWER(iso_code) = ?', [$onlyIso]);
            })
            ->orderBy('id')
            ->get();

        if ($countries->isEmpty()) {
            $this->warn('No countries found for sitemap generation.');

            return self::FAILURE;
        }

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

                Storage::disk('local')->put(
                    "sitemap-cache/{$iso2}/{$type}.json",
                    $response->getContent()
                );
            }

            $page = 1;
            do {
                $postsRequest = Request::create('/', 'GET', [
                    'country_iso2' => $iso2,
                    'page' => $page,
                    'per_page' => 1000,
                ]);
                $postsResponse = $controller->posts($postsRequest);
                $decoded = json_decode($postsResponse->getContent(), true);
                Storage::disk('local')->put(
                    "sitemap-cache/{$iso2}/posts-page-{$page}.json",
                    $postsResponse->getContent()
                );

                $lastPage = (int) ($decoded['meta']['last_page'] ?? 1);
                $page++;
            } while ($page <= $lastPage);
        }

        $countriesResponse = $controller->countries();
        Storage::disk('local')->put('sitemap-cache/countries.json', $countriesResponse->getContent());

        $this->info('Sitemap cache generation complete.');

        return self::SUCCESS;
    }
}
