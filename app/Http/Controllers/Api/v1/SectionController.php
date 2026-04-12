<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttributeResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\SectionResource;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\CategoryFollow;
use App\Models\Category;
use App\Models\City;
use App\Models\Country;
use App\Models\Post;
use App\Models\PostData;
use App\Models\PostReaction;
use App\Models\SectionFollow;
use App\Models\Section;
use App\Models\UserNotification;
use App\Models\User;
use Filament\Forms\Components\Field;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }
        $sections = Section::orderBy("sort_order", "asc")->get();
        return SectionResource::collection($sections);
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
            ])
            ->where(function ($q) {
                $q->whereNull('post_type')->orWhere('post_type', 'listing');
            });

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

            $criteria = [
                'post_id' => ['$in' => $candidateIds],
            ];

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
            if (count($and) > 0) {
                $criteria['$and'] = $and;
            }

            $matchedIds = [];
            try {
                $cursor = PostData::raw(function ($collection) use ($criteria) {
                    return $collection->find($criteria, ['projection' => ['post_id' => 1]]);
                });
                foreach ($cursor as $doc) {
                    if (isset($doc['post_id'])) {
                        $matchedIds[] = (int) $doc['post_id'];
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

        // Sorting
        $sort = (string) $request->get('sort', 'newest');
        if ($sort === 'oldest') {
            $postsQuery->orderBy('created_at', 'asc');
        } elseif ($sort === 'price_asc' && Schema::hasColumn('posts', 'price')) {
            $postsQuery->orderBy('price', 'asc')->orderBy('created_at', 'desc');
        } elseif ($sort === 'price_desc' && Schema::hasColumn('posts', 'price')) {
            $postsQuery->orderBy('price', 'desc')->orderBy('created_at', 'desc');
        } else {
            $postsQuery->orderBy('created_at', 'desc');
        }

        $perPage = (int) ($request->get('per_page') ?? 15);
        if ($perPage <= 0) $perPage = 15;
        $posts = $postsQuery->paginate($perPage);

        // Add liked_by_me from MongoDB (current state); likes_count remains on MySQL posts table.
        $items = collect($posts->items());
        $postIds = $items->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $likedByMeSet = [];
        if (Auth::check() && count($postIds) > 0) {
            $docs = PostReaction::query()
                ->whereIn('post_id', $postIds)
                ->where('user_id', (int) Auth::id())
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
    public function post(Request $request )
    {
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
        $payload['likes_count'] = (int) ($payload['likes_count'] ?? 0);
        $payload['comments_count'] = 0;

        $this->notifyFollowersAboutNewPost($post, (int) Auth::id());

        return response()->json([
            'success' => true,
            'status' => true,
            'data' => $payload,
            'message' => 'تم اضافه المنشور بنجاح',
        ]);
    }

    private function notifyFollowersAboutNewPost(Post $post, int $authorId): void
    {
        $sectionFollowerIds = SectionFollow::query()
            ->where('section_id', (int) $post->section_id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $categoryFollowerIds = [];
        if ($post->category_id) {
            $categoryFollowerIds = CategoryFollow::query()
                ->where('category_id', (int) $post->category_id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $targets = collect(array_merge($sectionFollowerIds, $categoryFollowerIds))
            ->filter(fn ($id) => $id > 0 && $id !== $authorId)
            ->unique()
            ->values();

        if ($targets->isEmpty()) {
            return;
        }

        $sectionName = $post->section?->name ?? 'القسم';
        $title = (string) $post->title;
        foreach ($targets as $userId) {
            UserNotification::create([
                'user_id' => (int) $userId,
                'type' => 'follow.new_post',
                'title_ar' => 'إعلان جديد في قسم تتابعه',
                'title_en' => 'New listing in a followed section',
                'body_ar' => $title !== '' ? mb_substr($title, 0, 180) : ('قسم: ' . $sectionName),
                'body_en' => $title !== '' ? mb_substr($title, 0, 180) : ('Section: ' . $sectionName),
                'url' => '/post/' . (int) $post->id,
                'data' => [
                    'post_id' => (int) $post->id,
                    'section_id' => (int) $post->section_id,
                    'category_id' => (int) ($post->category_id ?? 0),
                ],
            ]);
        }
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


}
