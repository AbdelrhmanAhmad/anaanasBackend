<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostReaction;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class HomeStatsController extends Controller
{
    /**
     * Top sections by ad-count growth: last 30 days vs previous 30 days.
     * Only sections with growth >= 1%. Max 6. Scoped by country_id when provided.
     */
    public function sectionMomentum(Request $request)
    {
        $land = (string) ($request->get('land') ?? '');
        $countryId = $request->filled('country_id') ? (int) $request->get('country_id') : null;
        $cacheKey = 'api:home:section-momentum:v1:'.($countryId ?? 'all').':'.($land !== '' ? $land : '_');

        $payload = Cache::remember($cacheKey, 90, function () use ($land, $countryId) {
            if ($land !== '') {
                app()->setLocale($land);
            }

            if (! Schema::hasColumn('posts', 'created_at')) {
                return [
                    'success' => true,
                    'data' => [],
                    'meta' => ['reason' => 'posts.created_at_unavailable'],
                ];
            }

            return $this->computeSectionMomentumPayload($countryId);
        });

        return response()->json($payload);
    }

    /**
     * @return array{success: true, data: array<int, array<string, mixed>>}
     */
    private function computeSectionMomentumPayload(?int $countryId): array
    {
        $now = now();
        $currentStart = $now->copy()->subDays(30);
        $previousStart = $now->copy()->subDays(60);
        $previousEnd = $now->copy()->subDays(30);

        $base = function () use ($countryId) {
            $q = Post::query()->whereNotNull('section_id');
            if ($countryId !== null && Schema::hasColumn('posts', 'country_id')) {
                $q->where('country_id', $countryId);
            }
            if (Schema::hasColumn('posts', 'status')) {
                $q->where('status', 'active');
            }

            return $q;
        };

        $currentCounts = (clone $base())
            ->where('created_at', '>=', $currentStart)
            ->where('created_at', '<', $now)
            ->selectRaw('section_id, COUNT(*) as c')
            ->groupBy('section_id')
            ->pluck('c', 'section_id');

        $previousCounts = (clone $base())
            ->where('created_at', '>=', $previousStart)
            ->where('created_at', '<', $previousEnd)
            ->selectRaw('section_id, COUNT(*) as c')
            ->groupBy('section_id')
            ->pluck('c', 'section_id');

        $sectionIds = $currentCounts->keys()->merge($previousCounts->keys())->unique()->values()->all();

        $rows = [];
        foreach ($sectionIds as $sid) {
            $sid = (int) $sid;
            if ($sid <= 0) {
                continue;
            }
            $cur = (int) ($currentCounts[$sid] ?? 0);
            $prev = (int) ($previousCounts[$sid] ?? 0);
            if ($prev === 0) {
                $growth = $cur > 0 ? 100.0 : 0.0;
            } else {
                $growth = round((($cur - $prev) / $prev) * 100, 1);
            }
            if ($growth < 1.0) {
                continue;
            }
            $rows[] = [
                'section_id' => $sid,
                'current_count' => $cur,
                'previous_count' => $prev,
                'growth_percent' => $growth,
            ];
        }

        usort($rows, fn ($a, $b) => $b['growth_percent'] <=> $a['growth_percent']);
        $rows = array_slice($rows, 0, 6);

        $sections = Section::query()
            ->whereIn('id', array_column($rows, 'section_id'))
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        $data = [];
        foreach ($rows as $row) {
            $sec = $sections->get($row['section_id']);
            if (! $sec) {
                continue;
            }
            $arr = $sec->toArray();
            $data[] = [
                'section_id' => $row['section_id'],
                'slug' => $arr['slug'] ?? '',
                'name' => $arr['name'] ?? '',
                'icon_full_path' => $arr['icon_full_path'] ?? null,
                'image_full_path' => $arr['image_full_path'] ?? null,
                'current_count' => $row['current_count'],
                'previous_count' => $row['previous_count'],
                'growth_percent' => $row['growth_percent'],
            ];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * Trending posts by engagement: comments + Mongo reactions. Max 6.
     */
    public function trendingPosts(Request $request)
    {
        $land = (string) ($request->get('land') ?? '');
        $countryId = $request->filled('country_id') ? (int) $request->get('country_id') : null;
        $limit = min(20, max(1, (int) ($request->get('limit') ?? 6)));
        $cacheKey = 'api:home:trending-posts:v1:'.($countryId ?? 'all').':'.($land !== '' ? $land : '_').':'.$limit;

        $payload = Cache::remember($cacheKey, 90, function () use ($land, $countryId, $limit) {
            if ($land !== '') {
                app()->setLocale($land);
            }

            return $this->computeTrendingPostsPayload($countryId, $limit);
        });

        return response()->json($payload);
    }

    /**
     * @return array{success: true, data: array<int, array<string, mixed>>}
     */
    private function computeTrendingPostsPayload(?int $countryId, int $limit): array
    {
        $q = Post::query()
            ->with(['section', 'category'])
            ->when($countryId !== null && Schema::hasColumn('posts', 'country_id'), fn ($qq) => $qq->where('country_id', $countryId));

        if (Schema::hasColumn('posts', 'status')) {
            $q->where('status', 'active');
        }

        if (Schema::hasColumn('posts', 'created_at')) {
            $q->where('created_at', '>=', now()->subDays(90));
        }

        $q->orderByDesc('id')->limit(400);
        $posts = $q->get();

        if ($posts->isEmpty()) {
            return ['success' => true, 'data' => []];
        }

        $postIds = $posts->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $commentCounts = [];
        if (Schema::hasTable('comments')) {
            $commentCounts = Comment::query()
                ->whereIn('post_id', $postIds)
                ->selectRaw('post_id, COUNT(*) as cnt')
                ->groupBy('post_id')
                ->pluck('cnt', 'post_id')
                ->mapWithKeys(fn ($cnt, $pid) => [(int) $pid => (int) $cnt])
                ->all();
        }

        $reactionCounts = [];
        try {
            $cursor = PostReaction::raw(function ($collection) use ($postIds) {
                return $collection->aggregate([
                    ['$match' => ['post_id' => ['$in' => $postIds]]],
                    ['$group' => ['_id' => '$post_id', 'cnt' => ['$sum' => 1]]],
                ]);
            });
            foreach ($cursor as $doc) {
                $pid = (int) ($doc['_id'] ?? 0);
                if ($pid > 0) {
                    $reactionCounts[$pid] = (int) ($doc['cnt'] ?? 0);
                }
            }
        } catch (\Throwable) {
            $reactionCounts = [];
        }

        $scored = [];
        foreach ($posts as $post) {
            $pid = (int) $post->id;
            $c = (int) ($commentCounts[$pid] ?? 0);
            $r = (int) ($reactionCounts[$pid] ?? 0);
            $score = $c + $r;
            if ($score <= 0) {
                continue;
            }
            $section = $post->section;
            $category = $post->category;
            $secArr = $section ? $section->toArray() : null;
            $catArr = $category ? $category->toArray() : null;
            $scored[] = [
                'post_id' => $pid,
                'title' => $post->title,
                'score' => $score,
                'comments_count' => $c,
                'reactions_count' => $r,
                'section_slug' => $section?->slug,
                'section_name' => $secArr['name'] ?? null,
                'category_slug' => $category?->slug,
                'category_name' => $catArr['name'] ?? null,
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, $limit);

        return [
            'success' => true,
            'data' => array_values($scored),
        ];
    }

    /**
     * Latest active listings for the home mobile strip (newest first).
     */
    public function latestListings(Request $request)
    {
        $land = (string) ($request->get('land') ?? '');
        $countryId = $request->filled('country_id') ? (int) $request->get('country_id') : null;
        $limit = min(20, max(1, (int) ($request->get('limit') ?? 12)));
        $userId = Auth::id();
        $cacheKey = 'api:home:latest-listings:v1:'
            .($countryId ?? 'all')
            .':'.($land !== '' ? $land : '_')
            .':'.$limit
            .':u'.($userId ? (int) $userId : 0);

        $payload = Cache::remember($cacheKey, 45, function () use ($land, $countryId, $limit) {
            if ($land !== '') {
                app()->setLocale($land);
            }

            return $this->computeLatestListingsPayload($countryId, $limit, $land);
        });

        return response()->json($payload);
    }

    /**
     * @return array{success: true, data: array<int, array<string, mixed>>}
     */
    private function computeLatestListingsPayload(?int $countryId, int $limit, string $land = ''): array
    {
        $q = Post::query()
            ->with([
                'city',
                'section',
                'category',
                'postImages' => fn ($qq) => $qq->orderBy('id'),
            ]);

        if (Schema::hasColumn('posts', 'post_type')) {
            $q->where(function ($qq) {
                $qq->whereNull('post_type')->orWhere('post_type', 'listing');
            });
        }

        if (Schema::hasColumn('posts', 'status')) {
            $q->where('status', 'active');
        }

        if ($countryId !== null && Schema::hasColumn('posts', 'country_id')) {
            $q->where('country_id', $countryId);
        }

        $newestOrderExpr = Schema::hasColumn('posts', 'publish_date')
            ? 'COALESCE(publish_date, created_at)'
            : 'created_at';
        $q->orderByRaw($newestOrderExpr.' desc')->limit($limit);

        $posts = $q->get();
        if ($posts->isEmpty()) {
            return ['success' => true, 'data' => []];
        }

        $postIds = $posts->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $reactionsByMe = [];
        if (count($postIds) > 0 && Auth::check()) {
            $myDocs = PostReaction::query()
                ->whereIn('post_id', $postIds)
                ->where('user_id', (int) Auth::id())
                ->get(['post_id', 'type']);
            foreach ($myDocs as $doc) {
                $reactionsByMe[(int) $doc->post_id] = (string) $doc->type;
            }
        }

        $currency = $land === 'en' ? 'JOD' : 'د.أ';
        $newSince = now()->subDays(5);

        $data = [];
        foreach ($posts as $post) {
            $pid = (int) $post->id;
            $ref = Schema::hasColumn('posts', 'publish_date') && $post->publish_date
                ? $post->publish_date
                : $post->created_at;
            $isNew = $ref && $ref->gte($newSince);

            $cityName = '';
            if ($post->city) {
                $cityArr = $post->city->toArray();
                $cityName = (string) ($cityArr['name'] ?? '');
            }

            $price = null;
            if (Schema::hasColumn('posts', 'price') && $post->price !== null) {
                $price = (float) $post->price;
            }

            $data[] = [
                'id' => $pid,
                'title' => (string) ($post->title ?? ''),
                'image_url' => $this->resolvePostListingImageUrl($post),
                'is_new' => (bool) $isNew,
                'location' => $cityName !== '' ? $cityName : '—',
                'price' => $price,
                'currency' => $currency,
                'price_suffix' => null,
                'is_favorited' => isset($reactionsByMe[$pid]),
            ];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    private function resolvePostListingImageUrl(Post $post): ?string
    {
        $post->loadMissing('postImages');
        $first = $post->postImages->sortBy('id')->first();
        if ($first && $first->image_full_url) {
            return $first->image_full_url;
        }

        $main = $post->main_image ?? null;
        if (! $main) {
            return null;
        }
        $main = (string) $main;
        if (preg_match('/^https?:\/\//i', $main)) {
            return $main;
        }
        try {
            if (str_starts_with($main, 'upload/')
                || str_starts_with($main, 'posts/')
                || str_starts_with($main, 'photos/')) {
                return Storage::disk('s3')->url($main);
            }

            return Storage::disk('public')->url($main);
        } catch (\Throwable) {
            return null;
        }
    }
}
