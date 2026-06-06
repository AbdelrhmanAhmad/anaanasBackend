<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttributeResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SectionResource;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Post;
use App\Models\PostData;
use App\Models\PostReaction;
use App\Models\Section;
use App\Models\User;
use App\Rules\NoForbiddenWords;
use App\Services\AccountVerificationService;
use App\Services\PostCreationRateLimitService;
use App\Services\PostModerationService;
use Filament\Forms\Components\Field;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        $land = (string) ($request->get('land') ?? '');
        $cacheKey = 'api:sections:index:v1:'.$land;

        $payload = Cache::remember($cacheKey, 300, function () use ($land) {
            if ($land !== '') {
                app()->setLocale($land);
            }
            $sections = Section::orderBy('sort_order', 'asc')->get();

            return SectionResource::collection($sections)->response()->getData(true);
        });

        return response()->json($payload);
    }

    public function getPosts(Request $request)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        // Resolve section/category from slugs (SEO-friendly URLs)
        $sectionId = null;
        $categoryId = null;

        if ($request->filled('section_slug')) {
            $sec = Section::query()->where('slug', $request->get('section_slug'))->first();
            $sectionId = $sec?->id;
        } elseif ($request->filled('section_id')) {
            $sectionId = (int) $request->get('section_id');
        }

        if ($request->filled('category_slug') && $sectionId) {
            $cat = Category::query()
                ->where('slug', $request->get('category_slug'))
                ->where('section_id', $sectionId)
                ->first();
            $categoryId = $cat?->id;
        } elseif ($request->filled('category_id')) {
            $categoryId = (int) $request->get('category_id');
        }

        $postsQuery = Post::query()
            ->with([
                "user",
                "category",
                "section",
                "city",
                "postImages",
            ]);

        // Older DBs may not have post_type yet — skip filter instead of 500
        if (Schema::hasColumn('posts', 'post_type')) {
            $postsQuery->where(function ($q) {
                $q->whereNull('post_type')->orWhere('post_type', 'listing');
            });
        }

        app(PostModerationService::class)->scopePubliclyVisible($postsQuery);

        // Comments are optional (older DBs may not have the table yet)
        if (Schema::hasTable('comments')) {
            $postsQuery
                ->with([
                    "comments" => function ($q) {
                        $q->whereNull('parent_id')
                            ->with('user')
                            ->latest()
                            ->take(2);
                    },
                ])
                ->withCount('comments');
        }

        // Base filters
        if ($sectionId) {
            $postsQuery->where('section_id', (int) $sectionId);
        }
        if ($categoryId) {
            $postsQuery->where('category_id', (int) $categoryId);
        }

        if ($request->filled('country_id') && Schema::hasColumn('posts', 'country_id')) {
            $postsQuery->where('country_id', (int) $request->get('country_id'));
        }
        if ($request->filled('city_id') && Schema::hasColumn('posts', 'city_id')) {
            $postsQuery->where('city_id', (int) $request->get('city_id'));
        }
        if ($request->filled('price_min') && Schema::hasColumn('posts', 'price')) {
            $postsQuery->where('price', '>=', (float) $request->get('price_min'));
        }
        if ($request->filled('price_max') && Schema::hasColumn('posts', 'price')) {
            $postsQuery->where('price', '<=', (float) $request->get('price_max'));
        }
        // Free-text search (title + description) with per-token AND matching
        if ($request->filled('q')) {
            $rawQ = trim((string) $request->get('q'));
            if ($rawQ !== '') {
                $hasTitle = Schema::hasColumn('posts', 'title');
                $hasDescription = Schema::hasColumn('posts', 'description');

                // Split on whitespace, keep tokens >= 2 chars (prevents noise), cap token count
                $tokens = array_slice(
                    array_values(array_filter(preg_split('/\s+/u', $rawQ) ?: [], function ($token) {
                        return mb_strlen((string) $token) >= 2;
                    })),
                    0,
                    6
                );

                if (count($tokens) === 0) {
                    $tokens = [$rawQ];
                }

                foreach ($tokens as $token) {
                    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $token) . '%';
                    $postsQuery->where(function ($sub) use ($like, $hasTitle, $hasDescription) {
                        if ($hasTitle) {
                            $sub->where('title', 'like', $like);
                        }
                        if ($hasDescription) {
                            $sub->orWhere('description', 'like', $like);
                        }
                        if (!$hasTitle && !$hasDescription) {
                            // Fallback (unlikely): no searchable columns -> yield no results
                            $sub->whereRaw('1 = 0');
                        }
                    });
                }
            }
        }

        if ($request->filled('has_images') && Schema::hasTable('post_images')) {
            $hasImagesRaw = strtolower((string) $request->get('has_images'));
            $hasImages = in_array($hasImagesRaw, ['1', 'true', 'yes'], true)
                ? true
                : (in_array($hasImagesRaw, ['0', 'false', 'no'], true) ? false : null);
            if ($hasImages === true) {
                $postsQuery->whereHas('postImages');
            } elseif ($hasImages === false) {
                $postsQuery->whereDoesntHave('postImages');
            }
        }

        // Attribute filters:
        // Preferred: attr[795][]=11880&attr[795][]=11881
        // Optional ranges: attr[900][from]=10&attr[900][to]=20
        // (Ranges need stored values to be effective; parsed for future use.)
        $attrFilters = [];
        $attrRanges = [];

        $attr = $request->query('attr', []);
        if (is_array($attr)) {
            foreach ($attr as $attrIdRaw => $val) {
                $attrId = (int) $attrIdRaw;
                if ($attrId <= 0) continue;

                // Range notation: attr[ID][from]/[to]
                if (is_array($val) && (array_key_exists('from', $val) || array_key_exists('to', $val))) {
                    $from = isset($val['from']) ? trim((string) $val['from']) : null;
                    $to = isset($val['to']) ? trim((string) $val['to']) : null;
                    $attrRanges[$attrId] = [
                        'from' => $from !== '' ? $from : null,
                        'to' => $to !== '' ? $to : null,
                    ];
                    continue;
                }

                // Option selections
                $vals = is_array($val) ? $val : [$val];
                $optIds = [];
                foreach ($vals as $v) {
                    if ($v === null) continue;
                    foreach (explode(',', (string) $v) as $p) {
                        $n = (int) trim($p);
                        if ($n > 0) $optIds[] = $n;
                    }
                }
                $optIds = array_values(array_unique($optIds));
                if (count($optIds) > 0) {
                    $attrFilters[$attrId] = $optIds;
                }
            }
        }

        // Backward compatibility: a{attributeId}=optionId (also supports a{attributeId}[]=...)
        foreach ($request->query() as $key => $value) {
            if (!is_string($key) || !str_starts_with($key, 'a')) continue;
            $attrId = (int) substr($key, 1);
            if ($attrId <= 0) continue;
            if (isset($attrFilters[$attrId])) continue;

            $vals = is_array($value) ? $value : [$value];
            $optIds = [];
            foreach ($vals as $v) {
                if ($v === null) continue;
                foreach (explode(',', (string) $v) as $p) {
                    $n = (int) trim($p);
                    if ($n > 0) $optIds[] = $n;
                }
            }
            $optIds = array_values(array_unique($optIds));
            if (count($optIds) > 0) {
                $attrFilters[$attrId] = $optIds;
            }
        }

        // If attribute filters exist, intersect via Mongo post_data
        if (count($attrFilters) > 0) {
            // Get candidate IDs from base filters (section/category/city/price/country already applied)
            $candidateIds = $postsQuery->clone()->select('id')->pluck('id')->map(fn($id) => (int) $id)->values()->all();

            if (count($candidateIds) === 0) {
                $perPage = (int) ($request->get('per_page') ?? 15);
                if ($perPage <= 0) $perPage = 15;
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                    'prev_page_url' => null,
                    'next_page_url' => null,
                ]);
            }

            $and = [];
            foreach ($attrFilters as $attrId => $optIds) {
                $and[] = [
                    'attributes' => [
                        '$elemMatch' => [
                            'attributeId' => (int) $attrId,
                            'optionId' => ['$in' => array_map('intval', $optIds)],
                        ],
                    ],
                ];
            }

            // Chunk $in to avoid oversized Mongo queries / BSON limits on large categories.
            $matchedIds = [];
            $chunkSize = 6000;
            try {
                foreach (array_chunk($candidateIds, $chunkSize) as $chunk) {
                    if (count($chunk) === 0) {
                        continue;
                    }
                    $criteria = [
                        'post_id' => ['$in' => $chunk],
                    ];
                    if (count($and) > 0) {
                        $criteria['$and'] = $and;
                    }
                    $cursor = PostData::raw(function ($collection) use ($criteria) {
                        return $collection->find($criteria, ['projection' => ['post_id' => 1]]);
                    });
                    foreach ($cursor as $doc) {
                        if (isset($doc['post_id'])) {
                            $matchedIds[] = (int) $doc['post_id'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // If Mongo query fails, return empty (fail-safe)
                $matchedIds = [];
            }

            $matchedIds = array_values(array_unique($matchedIds));
            if (count($matchedIds) === 0) {
                $perPage = (int) ($request->get('per_page') ?? 15);
                if ($perPage <= 0) $perPage = 15;
                return response()->json([
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'from' => null,
                    'to' => null,
                    'prev_page_url' => null,
                    'next_page_url' => null,
                ]);
            }

            $postsQuery->whereIn('id', $matchedIds);
        }

        // Sorting (prioritize publish_date when column exists)
        $newestOrderExpr = Schema::hasColumn('posts', 'publish_date')
            ? 'COALESCE(publish_date, created_at)'
            : 'created_at';
        $sort = (string) $request->get('sort', 'newest');
        if ($sort === 'oldest') {
            $postsQuery->orderByRaw($newestOrderExpr . ' asc');
        } elseif ($sort === 'price_asc' && Schema::hasColumn('posts', 'price')) {
            $postsQuery->orderBy('price', 'asc')->orderByRaw($newestOrderExpr . ' desc');
        } elseif ($sort === 'price_desc' && Schema::hasColumn('posts', 'price')) {
            $postsQuery->orderBy('price', 'desc')->orderByRaw($newestOrderExpr . ' desc');
        } else {
            $postsQuery->orderByRaw($newestOrderExpr . ' desc');
        }

        $perPage = (int) ($request->get('per_page') ?? 15);
        if ($perPage <= 0) $perPage = 15;
        $posts = $postsQuery->paginate($perPage);

        // Add reaction info from MongoDB (current state).
        $items = collect($posts->items());
        $postIds = $items->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $emptyReactionCounts = collect(PostReaction::allowedTypes())->mapWithKeys(fn ($type) => [$type => 0])->all();

        // Batch-load real per-post reaction aggregates so the frontend doesn't
        // need to issue an extra /api/posts/{id}/reactions request per card.
        $reactionsByMe = [];
        $reactionCountsByPost = [];
        if (count($postIds) > 0) {
            if (Auth::check()) {
                $myDocs = PostReaction::query()
                    ->whereIn('post_id', $postIds)
                    ->where('user_id', (int) Auth::id())
                    ->get(['post_id', 'type']);
                foreach ($myDocs as $doc) {
                    $reactionsByMe[(int) $doc->post_id] = (string) $doc->type;
                }
            }

            // PostReaction lives in MongoDB: SQL-style GROUP BY is not supported here.
            $allDocs = PostReaction::query()
                ->whereIn('post_id', $postIds)
                ->get(['post_id', 'type']);
            foreach ($allDocs as $doc) {
                $pid = (int) $doc->post_id;
                $type = (string) $doc->type;
                if (!isset($reactionCountsByPost[$pid])) {
                    $reactionCountsByPost[$pid] = $emptyReactionCounts;
                }
                $reactionCountsByPost[$pid][$type] = ($reactionCountsByPost[$pid][$type] ?? 0) + 1;
            }
        }

        $payload = $posts->toArray();
        $payload['data'] = $items->map(function (Post $p) use ($reactionsByMe, $emptyReactionCounts, $reactionCountsByPost) {
            $arr = $p->toArray();
            $reactionTypeByMe = $reactionsByMe[(int) $p->id] ?? null;
            $arr['liked_by_me'] = (bool) $reactionTypeByMe;
            $arr['reaction_type_by_me'] = $reactionTypeByMe;
            $counts = $reactionCountsByPost[(int) $p->id] ?? $emptyReactionCounts;
            $arr['reaction_counts'] = $counts;
            $arr['likes_count'] = (int) array_sum($counts);
            return $arr;
        })->values()->all();

        return response()->json($payload);

    }
    public function post(Request $request )
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        /** @var User|null $authUser */
        $authUser = Auth::user();
        $verification = app(AccountVerificationService::class)->statusForUser((int) Auth::id());

        if (! $verification['is_account_verified']) {
            $rateLimit = app(PostCreationRateLimitService::class)->check((int) Auth::id());
            if (! $rateLimit['can_create']) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'post_rate_limit',
                    'message' => $rateLimit['message'],
                    'reason' => $rateLimit['reason'],
                    'retry_after_seconds' => $rateLimit['retry_after_seconds'],
                    'posts_in_last_hour' => $rateLimit['posts_in_last_hour'],
                    'hourly_limit' => $rateLimit['hourly_limit'],
                    'interval_minutes' => $rateLimit['interval_minutes'],
                    'next_allowed_at' => $rateLimit['next_allowed_at'],
                    ...$verification,
                ], 429);
            }
        }

        $request->validate([
            'title' => ['required', 'string', 'max:255', new NoForbiddenWords()],
            'description' => ['required', 'string', new NoForbiddenWords()],
            'location' => ['nullable', 'string', 'max:500', new NoForbiddenWords()],
        ]);

        $content = \request()->all();
        unset($content['images']) ;
        $data = $content;
        $rawAttrs = $content['attributes'] ?? null;
        if (is_array($rawAttrs)) {
            $data['attributes'] = $rawAttrs;
        } elseif (is_string($rawAttrs) && $rawAttrs !== '') {
            $decoded = json_decode($rawAttrs, true);
            $data['attributes'] = is_array($decoded) ? $decoded : [];
        } else {
            $data['attributes'] = [];
        }
//        $data['attributes'] =$content['attributes'] ?? [];
        $data['user_id'] = Auth::id();
        $moderation = app(PostModerationService::class);
        $initialStatus = $moderation->initialStatusForUser($authUser);
        $data['status'] = $initialStatus;
        if ($initialStatus === Post::STATUS_ACTIVE && Schema::hasColumn('posts', 'publish_date')) {
            $data['publish_date'] = now();
        }
        $post = Post::create($data);
        $attr_collect = collect();
        foreach ($data["attributes"] as $attribute) {
            $item = [];
            $attr = Attribute::  select('id' , 'name','slug')-> find($attribute['attributeId']);;
            if ($attr) {
                $item['attribute'] = $attr->toArray(false);
                if (is_array($attribute['optionId'])) {
                    $Option = AttributeOption::
                    select('id' , 'name' ,'attribute_id' ,'slug')->
                    whereIn("id", $attribute['optionId'])->get();;
                    $item['options'] = $Option->toArray(false);
                } else {
                    $Option = AttributeOption::
                    select('id' , 'name' ,'attribute_id' ,'slug')->
                    find($attribute['optionId']);;
                    $item['option'] = $Option->toArray(false);
                }
                $attr_collect->push($item);
            }
        }

        /** @var User|null $authUser */
        $authUser = Auth::user();
        $data['user'] = $authUser?->toArray();
//        $data['city'] = Ci;
        $data["attributes_and_options"] = $attr_collect  ->toArray() ;
        $post->postData()->create($data);
        // لا نقرأ $post->postData هنا: يحمّل علاقة MongoDB ثم refresh() يحاول إعادة eager-loadها
        // فيرمي BadMethodCallException على MongoDB Query Builder (whereIntegerInRaw).

        /** =========================
         *  تخزين الصور
         *  ========================= */
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                if (! $image->isValid()) {
                    continue;
                }





                $prefix = 'onshorr_' . Str::random(12);
                $extension = $image->getClientOriginalExtension();

                $fileName = 'posts/' . $post->id . '/' . $prefix . '.' . $extension;

// نفس Key = upload/{file_name}
                $path = 'upload/photos/'.date("Y")."/" .date("m") ."/". $fileName;
              $r =    Storage::disk('s3')->put(
                  $path,
                    fopen($image->getRealPath(), 'r'),
                    [
                        'visibility' => 'public',
                        'ContentType' => $image->getMimeType(),
                        'ContentDisposition' => 'inline',
                    ]
                );
// تخزين المسار فقط (زي sengin)
                $post->postImages()->create([
                    'image' => $path,
                ]);

//                $path = $image->store(
//                    'content/upload/posts/' . $post->id,
//                    's3' // 👈 مهم
//                );
//
//
//                $post->postImages()->create([
//                    'image' => $path,
//                ]);
            }
        }

        // إرجاع نفس شكل عناصر قائمة «إعلاناتي» (مع العلاقات) لعرضها فوراً في الواجهة
        $post->unsetRelation('postData');
        $post->unsetRelation('postImages');
        $post->refresh();
        $post->load([
            'user',
            'category',
            'section',
            'city',
            'postImages',
        ]);

        // لا نستخدم loadCount('comments') هنا: نموذج Comment قد يكون على اتصال افتراضي يمر عبر MongoDB
        // فيفشل BadMethodCallException "This method is not supported by MongoDB".
        // منشور جديد لا تعليقات عليه أصلاً.
        $payload = $post->toArray();
        $payload['liked_by_me'] = false;
        $payload['reaction_type_by_me'] = null;
        $payload['reaction_counts'] = collect(PostReaction::allowedTypes())->mapWithKeys(fn ($type) => [$type => 0])->all();
        $payload['likes_count'] = (int) ($payload['likes_count'] ?? 0);
        $payload['comments_count'] = 0;

        if ($initialStatus === Post::STATUS_ACTIVE) {
            $moderation->afterPublish($post);
        } elseif (in_array($initialStatus, Post::pendingReviewStatuses(), true)) {
            $moderation->notifyAdminsOfPendingReview($post);
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $payload,
            'moderation_status' => $initialStatus,
            'message' => $initialStatus === Post::STATUS_PENDING_REVIEW
                ? __('post_moderation.submitted_for_review')
                : 'تم اضافه المنشور بنجاح',
        ]);
    }

    public function cities(Request $request )
    {
        $cities = City::where("country_id"  , $request->country_id)->get();
        return response()->json([
            'status' => true,
            'data' =>  $cities
        ]);
    }


    public function countries()
    {
        return response()->json([
            'status' => true,
            'data' => Country::/*whereIsActive(true)->*/get(),
        ]);
    }


    public function SectionCategories(Request $request)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }
        $categories = Category::where("section_id", $request->section_id)
            ->get();
        return CategoryResource::collection($categories);
    }

    public function subfields(Request $request)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }
        $option_id = $request->option_id;
        $attribute_id = $request->attribute_id;
        $attributes = Attribute::where("parent_option_id", $option_id)
            ->where("parent_id", $attribute_id)
            ->with([
                'attributeOptions' => function ($query) {
                    $query->withCount("children");
                }
            ])->get();
        return AttributeResource::collection($attributes);
    }

    public function fields(Request $request)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }
        $sectionId = $request->section_id;
        $categoryId = $request->category_id;
if ($sectionId =='20')       return AttributeResource::collection(collect());

        $attributes = Attribute::where("section_id", $sectionId)
            ->where("category_id", $categoryId)
            ->whereNull('parent_id')
            ->with([
                'attributeOptions' => function ($query) {
                    $query->withCount("children");
                }
            ])->get();


        return AttributeResource::collection($attributes);

    }

    public function creationLimit(Request $request)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        $status = app(PostCreationRateLimitService::class)->check((int) Auth::id());
        $verification = app(AccountVerificationService::class)->statusForUser((int) Auth::id());

        if ($verification['is_account_verified']) {
            $status['can_create'] = true;
            $status['reason'] = null;
            $status['retry_after_seconds'] = 0;
            $status['message'] = null;
            $status['next_allowed_at'] = null;
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($status, $verification),
        ]);
    }


}
