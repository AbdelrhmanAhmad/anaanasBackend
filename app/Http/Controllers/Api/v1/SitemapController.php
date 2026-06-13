<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Post;
use App\Models\Section;
use App\Services\PostModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SitemapController extends Controller
{
    private const CACHE_TTL_SECONDS = 86400;

    public function countries()
    {
        $payload = Cache::remember('sitemap:countries', self::CACHE_TTL_SECONDS, function () {
            $countries = Country::query()
                ->whereNotNull('iso2')
                ->orderBy('id')
                ->get(['id', 'iso2', 'iso_code', 'name', 'updated_at']);

            return [
                'generated_at' => now()->toIso8601String(),
                'data' => $countries->map(fn (Country $country) => [
                    'id' => (int) $country->id,
                    'iso2' => strtolower((string) ($country->iso2 ?: $country->iso_code)),
                    'updated_at' => optional($country->updated_at)->toIso8601String(),
                ])->values()->all(),
            ];
        });

        return response()->json(['success' => true, ...$payload]);
    }

    public function sections(Request $request)
    {
        $country = $this->resolveCountry($request);
        if (! $country) {
            return response()->json(['success' => false, 'message' => 'country required'], 422);
        }

        $cacheKey = 'sitemap:sections:'.$country->id;
        $payload = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($country) {
            $sections = Section::query()
                ->orderBy('sort_order')
                ->get(['id', 'slug', 'updated_at']);

            $items = [];
            foreach ($sections as $section) {
                $slug = (string) $section->slug;
                if ($slug === '') {
                    continue;
                }

                $items[] = [
                    'section_slug' => $slug,
                    'updated_at' => optional($section->updated_at)->toIso8601String(),
                ];

                $categories = $section->categories()->get(['slug', 'updated_at']);
                foreach ($categories as $category) {
                    $categorySlug = (string) $category->slug;
                    if ($categorySlug === '') {
                        continue;
                    }
                    $items[] = [
                        'section_slug' => $slug,
                        'category_slug' => $categorySlug,
                        'updated_at' => optional($category->updated_at)->toIso8601String(),
                    ];
                }
            }

            return [
                'country_iso2' => strtolower((string) ($country->iso2 ?: $country->iso_code)),
                'generated_at' => now()->toIso8601String(),
                'data' => $items,
            ];
        });

        return response()->json(['success' => true, ...$payload]);
    }

    public function cities(Request $request)
    {
        $country = $this->resolveCountry($request);
        if (! $country) {
            return response()->json(['success' => false, 'message' => 'country required'], 422);
        }

        $cacheKey = 'sitemap:cities:'.$country->id;
        $payload = Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($country) {
            $cities = City::query()
                ->where('country_id', (int) $country->id)
                ->orderBy('id')
                ->get(['id', 'updated_at']);

            $items = [];
            foreach ($cities as $city) {
                $hasPosts = Post::query()
                    ->where('country_id', (int) $country->id)
                    ->where('city_id', (int) $city->id)
                    ->when(Schema::hasColumn('posts', 'post_type'), function ($q) {
                        $q->where(function ($sub) {
                            $sub->whereNull('post_type')->orWhere('post_type', 'listing');
                        });
                    })
                    ->tap(fn ($q) => app(PostModerationService::class)->scopePubliclyVisible($q))
                    ->exists();

                if (! $hasPosts) {
                    continue;
                }

                $items[] = [
                    'city_id' => (int) $city->id,
                    'updated_at' => optional($city->updated_at)->toIso8601String(),
                ];
            }

            return [
                'country_iso2' => strtolower((string) ($country->iso2 ?: $country->iso_code)),
                'generated_at' => now()->toIso8601String(),
                'data' => $items,
            ];
        });

        return response()->json(['success' => true, ...$payload]);
    }

    public function posts(Request $request)
    {
        $country = $this->resolveCountry($request);
        if (! $country) {
            return response()->json(['success' => false, 'message' => 'country required'], 422);
        }

        $perPage = min(1000, max(1, (int) $request->get('per_page', 500)));
        $page = max(1, (int) $request->get('page', 1));

        $query = Post::query()
            ->where('country_id', (int) $country->id)
            ->when(Schema::hasColumn('posts', 'post_type'), function ($q) {
                $q->where(function ($sub) {
                    $sub->whereNull('post_type')->orWhere('post_type', 'listing');
                });
            })
            ->tap(fn ($q) => app(PostModerationService::class)->scopePubliclyVisible($q))
            ->with(['section:id,slug', 'category:id,slug'])
            ->orderByDesc('updated_at');

        $paginator = $query->paginate($perPage, [
            'id',
            'updated_at',
            'publish_date',
            'section_id',
            'category_id',
        ], 'page', $page);

        return response()->json([
            'success' => true,
            'country_iso2' => strtolower((string) ($country->iso2 ?: $country->iso_code)),
            'generated_at' => now()->toIso8601String(),
            'data' => collect($paginator->items())->map(function (Post $post) {
                return [
                    'id' => (int) $post->id,
                    'section_slug' => (string) ($post->section?->slug ?? ''),
                    'category_slug' => (string) ($post->category?->slug ?? ''),
                    'updated_at' => optional($post->updated_at)->toIso8601String(),
                    'publish_date' => optional($post->publish_date)->toIso8601String(),
                ];
            })->values()->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function cacheFile(string $type, string $iso2)
    {
        $path = "sitemap-cache/{$iso2}/{$type}.json";
        if (! Storage::disk('local')->exists($path)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function resolveCountry(Request $request): ?Country
    {
        if ($request->filled('country_id')) {
            return Country::query()->find((int) $request->get('country_id'));
        }

        $iso = strtolower(trim((string) $request->get('country_iso2', $request->get('country', ''))));
        if ($iso === '') {
            return null;
        }

        return Country::query()
            ->whereRaw('LOWER(iso2) = ?', [$iso])
            ->orWhereRaw('LOWER(iso_code) = ?', [$iso])
            ->first();
    }
}
