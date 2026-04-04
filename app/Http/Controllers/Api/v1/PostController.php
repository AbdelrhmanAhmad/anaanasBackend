<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Comment;
use App\Models\Post;
use App\Models\PostData;
use App\Models\PostEvent;
use App\Models\PostReaction;
use App\Models\PostImages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\UTCDateTime;

class PostController extends Controller
{
    private function toMongoUtc(Carbon $dt): UTCDateTime
    {
        return new UTCDateTime($dt->getTimestamp() * 1000);
    }

    private function bsonIdValue($id, string $key): ?string
    {
        if (! $id) return null;
        if (is_array($id)) {
            return isset($id[$key]) ? (string) $id[$key] : null;
        }
        if ($id instanceof \ArrayAccess) {
            try {
                $v = $id[$key] ?? null;
                return $v !== null ? (string) $v : null;
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if (is_object($id) && isset($id->{$key})) {
            return (string) $id->{$key};
        }
        return null;
    }

    /**
     * Show single post details (MySQL post + Mongo post_data).
     * GET /api/posts/{post}
     */
    public function show(Request $request, Post $post)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        $post->load([
            'user',
            'category',
            'section',
            'city',
            'postImages',
        ]);

        if (Schema::hasTable('comments')) {
            $post->loadCount('comments');
            $post->load([
                'comments' => function ($q) {
                    $q->whereNull('parent_id')
                        ->with('user')
                        ->latest()
                        ->take(10);
                },
            ]);
        }

        $likedByMe = false;
        if (Auth::check()) {
            $likedByMe = PostReaction::query()
                ->where('post_id', (int) $post->id)
                ->where('user_id', (int) Auth::id())
                ->where('type', 'like')
                ->exists();
        }

        // MongoDB details (avoid eager loading cross-connection issues)
        $postData = PostData::query()
            ->where('post_id', (int) $post->id)
            ->first();

        $payload = $post->toArray();
        $payload['liked_by_me'] = $likedByMe;
        $payload['likes_count'] = (int) ($payload['likes_count'] ?? 0);
        $payload['post_data'] = $postData ? $postData->toArray() : null;

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * Post statistics (owner only).
     * GET /api/posts/{post}/statistics?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function statistics(Request $request, Post $post)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ((int) $post->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = !empty($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $from = !empty($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : (clone $to)->subDays(29)->startOfDay();

        $startUtc = $this->toMongoUtc($from);
        $endUtc = $this->toMongoUtc($to);

        $events = [
            'post_impression',
            'post_view',
            'post_share',
            'post_call',
            'post_chat_open',
            'post_like',
            'post_unlike',
            'post_comment',
        ];

        $baseMatch = [
            'post_id' => (int) $post->id,
            'event' => ['$in' => $events],
            'created_at' => ['$gte' => $startUtc, '$lte' => $endUtc],
        ];

        // Daily counts by event
        $dailyAgg = PostEvent::raw(function ($collection) use ($baseMatch) {
            return $collection->aggregate([
                ['$match' => $baseMatch],
                ['$addFields' => [
                    'day' => [
                        '$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at'],
                    ],
                ]],
                ['$group' => [
                    '_id' => ['day' => '$day', 'event' => '$event'],
                    'count' => ['$sum' => 1],
                ]],
            ]);
        });

        $dailyMap = [];
        $breakdownMap = [];
        foreach ($dailyAgg as $row) {
            $id = $row->_id ?? null;
            $day = $this->bsonIdValue($id, 'day') ?? '';
            $event = $this->bsonIdValue($id, 'event') ?? '';
            $count = (int) ($row->count ?? 0);
            if (! $day || ! $event) continue;

            $dailyMap[$day] = $dailyMap[$day] ?? [];
            $dailyMap[$day][$event] = $count;

            $breakdownMap[$event] = (int) ($breakdownMap[$event] ?? 0) + $count;
        }

        // Daily unique impressions (unique per day by user_id OR client_id OR ip)
        $uniqueDailyAgg = PostEvent::raw(function ($collection) use ($post, $startUtc, $endUtc) {
            return $collection->aggregate([
                ['$match' => [
                    'post_id' => (int) $post->id,
                    'event' => 'post_impression',
                    'created_at' => ['$gte' => $startUtc, '$lte' => $endUtc],
                ]],
                ['$addFields' => [
                    'day' => [
                        '$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at'],
                    ],
                    'uniq_key' => [
                        '$cond' => [
                            ['$ne' => ['$user_id', null]],
                            ['$concat' => ['u:', ['$toString' => '$user_id']]],
                            [
                                '$cond' => [
                                    ['$ne' => ['$meta.client_id', null]],
                                    ['$concat' => ['c:', ['$toString' => '$meta.client_id']]],
                                    ['$concat' => ['ip:', ['$ifNull' => ['$meta.ip', 'unknown']]]],
                                ],
                            ],
                        ],
                    ],
                ]],
                ['$group' => [
                    '_id' => ['day' => '$day', 'key' => '$uniq_key'],
                ]],
                ['$group' => [
                    '_id' => '$_id.day',
                    'count' => ['$sum' => 1],
                ]],
            ]);
        });

        $uniqueDailyMap = [];
        foreach ($uniqueDailyAgg as $row) {
            $day = (string) ($row->_id ?? '');
            $uniqueDailyMap[$day] = (int) ($row->count ?? 0);
        }

        // Unique impressions total
        $uniqueTotalAgg = PostEvent::raw(function ($collection) use ($post, $startUtc, $endUtc) {
            return $collection->aggregate([
                ['$match' => [
                    'post_id' => (int) $post->id,
                    'event' => 'post_impression',
                    'created_at' => ['$gte' => $startUtc, '$lte' => $endUtc],
                ]],
                ['$addFields' => [
                    'uniq_key' => [
                        '$cond' => [
                            ['$ne' => ['$user_id', null]],
                            ['$concat' => ['u:', ['$toString' => '$user_id']]],
                            [
                                '$cond' => [
                                    ['$ne' => ['$meta.client_id', null]],
                                    ['$concat' => ['c:', ['$toString' => '$meta.client_id']]],
                                    ['$concat' => ['ip:', ['$ifNull' => ['$meta.ip', 'unknown']]]],
                                ],
                            ],
                        ],
                    ],
                ]],
                ['$group' => ['_id' => '$uniq_key']],
                ['$count' => 'count'],
            ]);
        });
        $uniqueImpressionsTotal = 0;
        foreach ($uniqueTotalAgg as $row) {
            $uniqueImpressionsTotal = (int) ($row->count ?? 0);
            break;
        }

        // Top user agents (impressions only)
        $topUaAgg = PostEvent::raw(function ($collection) use ($post, $startUtc, $endUtc) {
            return $collection->aggregate([
                ['$match' => [
                    'post_id' => (int) $post->id,
                    'event' => 'post_impression',
                    'created_at' => ['$gte' => $startUtc, '$lte' => $endUtc],
                ]],
                ['$group' => [
                    '_id' => ['$ifNull' => ['$meta.user_agent', 'unknown']],
                    'count' => ['$sum' => 1],
                ]],
                ['$sort' => ['count' => -1]],
                ['$limit' => 10],
            ]);
        });
        $topUserAgents = [];
        foreach ($topUaAgg as $row) {
            $topUserAgents[] = [
                'user_agent' => (string) ($row->_id ?? 'unknown'),
                'count' => (int) ($row->count ?? 0),
            ];
        }

        // Build complete daily array (fill missing days with zeros)
        $days = [];
        $cursor = (clone $from);
        while ($cursor->lte($to)) {
            $days[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        $daily = [];
        foreach ($days as $day) {
            $counts = $dailyMap[$day] ?? [];
            $daily[] = [
                'date' => $day,
                'impressions' => (int) ($counts['post_impression'] ?? 0),
                'unique_impressions' => (int) ($uniqueDailyMap[$day] ?? 0),
                'views' => (int) ($counts['post_view'] ?? 0),
                'calls' => (int) ($counts['post_call'] ?? 0),
                'shares' => (int) ($counts['post_share'] ?? 0),
                'chats' => (int) ($counts['post_chat_open'] ?? 0),
                'likes' => (int) ($counts['post_like'] ?? 0),
                'unlikes' => (int) ($counts['post_unlike'] ?? 0),
                'comments' => (int) ($counts['post_comment'] ?? 0),
            ];
        }

        $totals = [
            'impressions' => (int) ($breakdownMap['post_impression'] ?? 0),
            'unique_impressions' => (int) $uniqueImpressionsTotal,
            'views' => (int) ($breakdownMap['post_view'] ?? 0),
            'calls' => (int) ($breakdownMap['post_call'] ?? 0),
            'shares' => (int) ($breakdownMap['post_share'] ?? 0),
            'chats' => (int) ($breakdownMap['post_chat_open'] ?? 0),
            'likes' => (int) ($breakdownMap['post_like'] ?? 0),
            'unlikes' => (int) ($breakdownMap['post_unlike'] ?? 0),
            'comments' => (int) ($breakdownMap['post_comment'] ?? 0),
        ];

        $breakdown = [];
        foreach ($events as $ev) {
            $breakdown[] = ['event' => $ev, 'count' => (int) ($breakdownMap[$ev] ?? 0)];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => (int) $post->id,
                'range' => [
                    'from' => $from->format('Y-m-d'),
                    'to' => $to->format('Y-m-d'),
                ],
                'totals' => $totals,
                'daily' => $daily,
                'breakdown' => $breakdown,
                'top' => [
                    'user_agents' => $topUserAgents,
                ],
            ],
        ]);
    }

    /**
     * Update a post (owner only).
     * POST /api/posts/{post}
     *
     * Notes:
     * - section_id/category_id are NOT allowed to change (ignored if sent).
     * - Supports multipart with images[] (will append new images).
     */
    public function update(Request $request, Post $post)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ((int) $post->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'country_id' => ['nullable', 'integer'],
            'city_id' => ['nullable', 'integer'],
            'attributes' => ['nullable'],
            'images' => ['nullable'],
            'images.*' => ['file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // Parse attributes (may come as JSON string from multipart)
        $attributesRaw = $request->input('attributes', []);
        if (is_string($attributesRaw)) {
            $decoded = json_decode($attributesRaw, true);
            $attributes = is_array($decoded) ? $decoded : [];
        } elseif (is_array($attributesRaw)) {
            $attributes = $attributesRaw;
        } else {
            $attributes = [];
        }

        // Update MySQL post (do NOT allow changing section/category)
        $post->fill([
            'title' => $request->has('title') ? $validated['title'] : $post->title,
            'description' => $request->has('description') ? $validated['description'] : $post->description,
            'price' => $request->has('price') ? $validated['price'] : $post->price,
            'country_id' => $request->has('country_id') ? $validated['country_id'] : $post->country_id,
            'city_id' => $request->has('city_id') ? $validated['city_id'] : $post->city_id,
        ]);
        $post->save();

        // Build attributes_and_options (same as create flow)
        $attrCollect = collect();
        foreach ($attributes as $attribute) {
            if (!is_array($attribute)) continue;
            $attributeId = $attribute['attributeId'] ?? null;
            $optionId = $attribute['optionId'] ?? null;
            if (! $attributeId) continue;

            $item = [];
            $attr = Attribute::select('id', 'name', 'slug')->find($attributeId);
            if (! $attr) continue;

            $item['attribute'] = $attr->toArray(false);

            if (is_array($optionId)) {
                $options = AttributeOption::select('id', 'name', 'attribute_id', 'slug')
                    ->whereIn('id', $optionId)
                    ->get();
                $item['options'] = $options->toArray(false);
            } else {
                $opt = AttributeOption::select('id', 'name', 'attribute_id', 'slug')->find($optionId);
                $item['option'] = $opt ? $opt->toArray(false) : null;
            }

            $attrCollect->push($item);
        }

        // Update Mongo post_data
        $postData = PostData::query()->where('post_id', (int) $post->id)->first();
        $payload = [
            'post_id' => (int) $post->id,
            'user_id' => (int) $user->id,
            'title' => $post->title,
            'description' => $post->description,
            'price' => $post->price,
            'country_id' => $post->country_id,
            'city_id' => $post->city_id,
            'section_id' => $post->section_id,
            'category_id' => $post->category_id,
            'attributes' => $attributes,
            'attributes_and_options' => $attrCollect->toArray(),
        ];

        if ($postData) {
            $postData->update($payload);
        } else {
            $post->postData()->create($payload);
        }

        // Append new images if provided
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if (! $image || ! $image->isValid()) continue;
                $path = $image->store('posts/' . $post->id, 'public');
                $post->postImages()->create(['image' => $path]);
            }
        }

        $post->load(['user', 'category', 'section', 'postImages']);
        $postDataFresh = PostData::query()->where('post_id', (int) $post->id)->first();

        $out = $post->toArray();
        $out['post_data'] = $postDataFresh ? $postDataFresh->toArray() : null;

        return response()->json([
            'success' => true,
            'data' => $out,
            'message' => 'تم تعديل المنشور بنجاح',
        ]);
    }

    /**
     * Delete a single post image (owner only).
     * DELETE /api/posts/{post}/images/{image}
     */
    public function deleteImage(Request $request, Post $post, PostImages $image)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if ((int) $post->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ((int) $image->post_id !== (int) $post->id) {
            return response()->json(['success' => false, 'message' => 'Image does not belong to this post'], 422);
        }

        // Delete file from storage (best-effort)
        try {
            if ($image->image) {
                Storage::disk('public')->delete($image->image);
            }
        } catch (\Throwable $e) {
            // ignore file deletion errors
        }

        $image->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الصورة بنجاح',
        ]);
    }

    /**
     * Overall statistics for authenticated user's posts.
     * GET /api/posts/my-statistics?from=YYYY-MM-DD&to=YYYY-MM-DD
     */
    public function myStatistics(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = !empty($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $from = !empty($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : (clone $to)->subDays(29)->startOfDay();

        $totalPosts = Post::withTrashed()->where('user_id', (int) $user->id)->count();
        $activePosts = Post::query()->where('user_id', (int) $user->id)->count();
        $deletedPosts = Post::onlyTrashed()->where('user_id', (int) $user->id)->count();

        $statusBreakdown = Post::query()
            ->where('user_id', (int) $user->id)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(function ($row) {
                return [
                    'status' => $row->status ?? null,
                    'count' => (int) ($row->count ?? 0),
                ];
            })
            ->values()
            ->all();

        $postIds = Post::withTrashed()
            ->where('user_id', (int) $user->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $commentsCount = 0;
        if (Schema::hasTable('comments') && count($postIds) > 0) {
            $commentsCount = Comment::query()
                ->whereIn('post_id', $postIds)
                ->count();
        }

        $events = [
            'post_impression',
            'post_view',
            'post_share',
            'post_call',
            'post_chat_open',
            'post_like',
            'post_unlike',
            'post_comment',
        ];

        $startUtc = $this->toMongoUtc($from);
        $endUtc = $this->toMongoUtc($to);

        $dailyMap = [];
        $breakdownMap = [];

        if (count($postIds) > 0) {
            $baseMatch = [
                'post_id' => ['$in' => $postIds],
                'event' => ['$in' => $events],
                'created_at' => ['$gte' => $startUtc, '$lte' => $endUtc],
            ];

            $dailyAgg = PostEvent::raw(function ($collection) use ($baseMatch) {
                return $collection->aggregate([
                    ['$match' => $baseMatch],
                    ['$addFields' => [
                        'day' => [
                            '$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$created_at'],
                        ],
                    ]],
                    ['$group' => [
                        '_id' => ['day' => '$day', 'event' => '$event'],
                        'count' => ['$sum' => 1],
                    ]],
                ]);
            });

            foreach ($dailyAgg as $row) {
                $id = $row->_id ?? null;
                $day = $this->bsonIdValue($id, 'day') ?? '';
                $event = $this->bsonIdValue($id, 'event') ?? '';
                $count = (int) ($row->count ?? 0);
                if (! $day || ! $event) continue;

                $dailyMap[$day] = $dailyMap[$day] ?? [];
                $dailyMap[$day][$event] = $count;

                $breakdownMap[$event] = (int) ($breakdownMap[$event] ?? 0) + $count;
            }
        }

        $days = [];
        $cursor = (clone $from);
        while ($cursor->lte($to)) {
            $days[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        $daily = [];
        foreach ($days as $day) {
            $counts = $dailyMap[$day] ?? [];
            $daily[] = [
                'date' => $day,
                'impressions' => (int) ($counts['post_impression'] ?? 0),
                'views' => (int) ($counts['post_view'] ?? 0),
                'calls' => (int) ($counts['post_call'] ?? 0),
                'shares' => (int) ($counts['post_share'] ?? 0),
                'chats' => (int) ($counts['post_chat_open'] ?? 0),
                'likes' => (int) ($counts['post_like'] ?? 0),
                'unlikes' => (int) ($counts['post_unlike'] ?? 0),
                'comments' => (int) ($counts['post_comment'] ?? 0),
            ];
        }

        $totals = [
            'total_posts' => (int) $totalPosts,
            'active_posts' => (int) $activePosts,
            'deleted_posts' => (int) $deletedPosts,
            'comments' => (int) $commentsCount,
            'impressions' => (int) ($breakdownMap['post_impression'] ?? 0),
            'views' => (int) ($breakdownMap['post_view'] ?? 0),
            'calls' => (int) ($breakdownMap['post_call'] ?? 0),
            'shares' => (int) ($breakdownMap['post_share'] ?? 0),
            'chats' => (int) ($breakdownMap['post_chat_open'] ?? 0),
            'likes' => (int) ($breakdownMap['post_like'] ?? 0),
            'unlikes' => (int) ($breakdownMap['post_unlike'] ?? 0),
            'post_comments' => (int) ($breakdownMap['post_comment'] ?? 0),
        ];
        $totals['interactions'] = (int) (
            ($totals['calls'] ?? 0) +
            ($totals['shares'] ?? 0) +
            ($totals['chats'] ?? 0) +
            ($totals['likes'] ?? 0) +
            ($totals['post_comments'] ?? 0)
        );

        $breakdown = [];
        foreach ($events as $ev) {
            $breakdown[] = ['event' => $ev, 'count' => (int) ($breakdownMap[$ev] ?? 0)];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'range' => [
                    'from' => $from->format('Y-m-d'),
                    'to' => $to->format('Y-m-d'),
                ],
                'totals' => $totals,
                'daily' => $daily,
                'breakdown' => $breakdown,
                'status_breakdown' => $statusBreakdown,
            ],
        ]);
    }

    /**
     * Get authenticated user's posts.
     * GET /api/posts/my-posts
     */
    public function myPosts(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        $perPage = (int) ($request->get('per_page') ?? 15);
        if ($perPage <= 0) $perPage = 15;

        $postsQuery = Post::query()
            ->where('user_id', (int) $user->id)
            ->with([
                'user',
                'category',
                'section',
                'city',
                'postImages',
            ])
            ->latest();

        if (Schema::hasTable('comments')) {
            $postsQuery
                ->with([
                    'comments' => function ($q) {
                        $q->whereNull('parent_id')
                            ->with('user')
                            ->latest()
                            ->take(2);
                    },
                ])
                ->withCount('comments');
        }

        $posts = $postsQuery->paginate($perPage);

        // Set path for pagination URLs to avoid route('login') errors
        $posts->setPath($request->url());

        $items = collect($posts->items());
        $postIds = $items->pluck('id')->map(fn($id) => (int) $id)->values()->all();

        // Get liked_by_me status
        $likedByMeSet = [];
        if (count($postIds) > 0) {
            $docs = PostReaction::query()
                ->whereIn('post_id', $postIds)
                ->where('user_id', (int) $user->id)
                ->where('type', 'like')
                ->get(['post_id']);

            foreach ($docs as $doc) {
                $likedByMeSet[(int) $doc->post_id] = true;
            }
        }

        $payload = $posts->toArray();
        $payload['data'] = $items->map(function (Post $p) use ($likedByMeSet) {
            $arr = $p->toArray();
            $arr['liked_by_me'] = (bool) ($likedByMeSet[(int) $p->id] ?? false);
            $arr['likes_count'] = (int) ($arr['likes_count'] ?? 0);
            return $arr;
        })->values()->all();

        return response()->json($payload);
    }

    /**
     * Get authenticated user's post images.
     * GET /api/posts/my-images
     */
    public function myImages(Request $request)
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $perPage = (int) ($request->get('per_page') ?? 20);
        if ($perPage <= 0) $perPage = 20;

        $imagesQuery = PostImages::query()
            ->whereHas('post', function ($q) use ($user) {
                $q->where('user_id', (int) $user->id);
            })
            ->with(['post' => function ($q) {
                $q->select('id', 'title', 'user_id');
            }])
            ->latest();

        $images = $imagesQuery->paginate($perPage);

        // Set path for pagination URLs to avoid route('login') errors
        $images->setPath($request->url());

        $payload = $images->toArray();
        $payload['data'] = collect($images->items())->map(function ($img) {
            $arr = $img->toArray();
            // Add full URL
            $arr['image_full_url'] = $img->image ? Storage::disk('public')->url($img->image) : null;
            return $arr;
        })->values()->all();

        return response()->json($payload);
    }
}


