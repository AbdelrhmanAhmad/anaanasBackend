<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\AuctionBid;
use App\Models\AuctionLot;
use App\Models\AuctionWatcher;
use App\Models\Post;
use App\Models\PostData;
use App\Models\PostImages;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuctionController extends Controller
{
    public function index(Request $request)
    {
        $land = $request->get('land');
        if ($land) {
            app()->setLocale($land);
        }

        $query = AuctionLot::query()
            ->with([
                'post.user',
                'post.section',
                'post.category',
                'post.city',
                'post.postImages',
                'winner',
            ])
            ->withCount('watchers');

        // filters
        $query->whereHas('post', function ($q) use ($request) {
            $q->where('post_type', 'auction');

            if ($request->filled('section_id')) {
                $q->where('section_id', (int) $request->get('section_id'));
            }
            if ($request->filled('category_id')) {
                $q->where('category_id', (int) $request->get('category_id'));
            }
            if ($request->filled('country_id')) {
                $q->where('country_id', (int) $request->get('country_id'));
            }
            if ($request->filled('city_id')) {
                $q->where('city_id', (int) $request->get('city_id'));
            }
            if ($request->filled('price_min')) {
                $q->where('price', '>=', (float) $request->get('price_min'));
            }
            if ($request->filled('price_max')) {
                $q->where('price', '<=', (float) $request->get('price_max'));
            }
            if ($request->filled('q')) {
                $term = trim((string) $request->get('q'));
                if ($term !== '') {
                    $q->where(function ($w) use ($term) {
                        $w->where('title', 'like', '%' . $term . '%')
                          ->orWhere('description', 'like', '%' . $term . '%');
                    });
                }
            }

            if ($request->filled('has_images')) {
                $hasImagesRaw = strtolower((string) $request->get('has_images'));
                $hasImages = in_array($hasImagesRaw, ['1', 'true', 'yes'], true)
                    ? true
                    : (in_array($hasImagesRaw, ['0', 'false', 'no'], true) ? false : null);
                if ($hasImages === true) {
                    $q->whereHas('postImages');
                } elseif ($hasImages === false) {
                    $q->whereDoesntHave('postImages');
                }
            }
        });

        if ($request->filled('status')) {
            $query->where('status', (string) $request->get('status'));
        }

        $sort = (string) $request->get('sort', 'newest');
        if ($sort === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($sort === 'price_asc') {
            $query->orderBy('current_price', 'asc')->orderBy('created_at', 'desc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('current_price', 'desc')->orderBy('created_at', 'desc');
        } elseif ($sort === 'ending_soon') {
            $query->orderBy('end_at', 'asc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $perPage = max(1, (int) $request->get('per_page', 15));
        $lots = $query->paginate($perPage);

        return response()->json($lots);
    }

    public function show(Request $request, Post $post)
    {
        if ($post->post_type !== 'auction') {
            return response()->json(['success' => false, 'message' => 'Auction not found'], 404);
        }

        $lot = AuctionLot::query()
            ->with([
                'post.user',
                'post.section',
                'post.category',
                'post.city',
                'post.postImages',
                'winner',
                'bids.user',
            ])
            ->where('post_id', (int) $post->id)
            ->first();

        if (!$lot) {
            return response()->json(['success' => false, 'message' => 'Auction lot not found'], 404);
        }

        $postData = PostData::query()->where('post_id', (int) $post->id)->first();
        $payload = $lot->toArray();
        $payload['post_data'] = $postData ? $postData->toArray() : null;

        if (Auth::check()) {
            $payload['watched_by_me'] = AuctionWatcher::query()
                ->where('auction_lot_id', (int) $lot->id)
                ->where('user_id', (int) Auth::id())
                ->exists();
        } else {
            $payload['watched_by_me'] = false;
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function store(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'city_id' => ['required', 'integer', 'exists:cities,id'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_price' => ['required', 'numeric', 'min:0.01'],
            'min_increment' => ['nullable', 'numeric', 'min:0.01'],
            'reserve_price' => ['nullable', 'numeric', 'min:0'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['required', 'date', 'after:now'],
            'attributes' => ['nullable'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
        ]);

        $attributes = $this->decodeAttributes($request->input('attributes'));
        $startPrice = (float) $validated['start_price'];

        $post = DB::transaction(function () use ($validated, $user, $startPrice) {
            $post = Post::query()->create([
                'user_id' => (int) $user->id,
                'section_id' => (int) $validated['section_id'],
                'category_id' => (int) $validated['category_id'],
                'country_id' => (int) $validated['country_id'],
                'city_id' => (int) $validated['city_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'price' => $startPrice,
                'status' => 'active',
                'post_type' => 'auction',
            ]);

            AuctionLot::query()->create([
                'post_id' => (int) $post->id,
                'start_price' => $startPrice,
                'current_price' => $startPrice,
                'min_increment' => isset($validated['min_increment']) ? (float) $validated['min_increment'] : 1,
                'reserve_price' => isset($validated['reserve_price']) ? (float) $validated['reserve_price'] : null,
                'start_at' => !empty($validated['start_at']) ? Carbon::parse($validated['start_at']) : now(),
                'end_at' => Carbon::parse($validated['end_at']),
                'status' => 'live',
            ]);

            return $post;
        });

        $this->savePostData((int) $post->id, (int) $user->id, $validated, $attributes, true);
        $this->storeImages($request, $post);

        return response()->json([
            'success' => true,
            'data' => $post->load(['postImages', 'auctionLot']),
            'message' => 'Auction created successfully',
        ], 201);
    }

    public function update(Request $request, Post $post)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        if ($post->post_type !== 'auction') return response()->json(['success' => false, 'message' => 'Auction not found'], 404);
        if ((int) $post->user_id !== (int) $user->id) return response()->json(['success' => false, 'message' => 'Forbidden'], 403);

        $lot = AuctionLot::query()->where('post_id', (int) $post->id)->first();
        if (!$lot) return response()->json(['success' => false, 'message' => 'Auction lot not found'], 404);

        $validated = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'min_increment' => ['nullable', 'numeric', 'min:0.01'],
            'reserve_price' => ['nullable', 'numeric', 'min:0'],
            'end_at' => ['nullable', 'date', 'after:now'],
            'status' => ['nullable', 'in:draft,live,ended,cancelled'],
            'attributes' => ['nullable'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:5120'],
        ]);

        $post->fill([
            'city_id' => $validated['city_id'] ?? $post->city_id,
            'country_id' => $validated['country_id'] ?? $post->country_id,
            'title' => $validated['title'] ?? $post->title,
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $post->description,
        ]);
        $post->save();

        $lot->fill([
            'min_increment' => isset($validated['min_increment']) ? (float) $validated['min_increment'] : $lot->min_increment,
            'reserve_price' => array_key_exists('reserve_price', $validated) ? (float) $validated['reserve_price'] : $lot->reserve_price,
            'end_at' => !empty($validated['end_at']) ? Carbon::parse($validated['end_at']) : $lot->end_at,
            'status' => $validated['status'] ?? $lot->status,
        ]);
        $lot->save();

        $attributes = $this->decodeAttributes($request->input('attributes'));
        $this->savePostData((int) $post->id, (int) $user->id, $validated, $attributes, false);
        $this->storeImages($request, $post);

        return response()->json([
            'success' => true,
            'data' => $post->load(['postImages', 'auctionLot']),
            'message' => 'Auction updated successfully',
        ]);
    }

    public function destroy(Request $request, Post $post)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        if ($post->post_type !== 'auction') return response()->json(['success' => false, 'message' => 'Auction not found'], 404);
        if ((int) $post->user_id !== (int) $user->id) return response()->json(['success' => false, 'message' => 'Forbidden'], 403);

        $post->delete();
        return response()->json([
            'success' => true,
            'message' => 'Auction deleted successfully',
        ]);
    }

    public function bid(Request $request, Post $post)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        if ($post->post_type !== 'auction') return response()->json(['success' => false, 'message' => 'Auction not found'], 404);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $amount = (float) $validated['amount'];

        try {
            $result = DB::transaction(function () use ($post, $user, $amount) {
                $lot = AuctionLot::query()
                    ->where('post_id', (int) $post->id)
                    ->lockForUpdate()
                    ->first();

                if (!$lot) {
                    abort(404, 'Auction lot not found');
                }
                if ($lot->status !== 'live') {
                    abort(422, 'Auction is not active');
                }
                if ($lot->end_at && Carbon::parse($lot->end_at)->isPast()) {
                    abort(422, 'Auction already ended');
                }
                if ((int) $post->user_id === (int) $user->id) {
                    abort(422, 'Owner cannot bid on own auction');
                }

                $minAllowed = (float) $lot->current_price + (float) $lot->min_increment;
                if ($amount < $minAllowed) {
                    abort(422, 'Bid must be at least current price + min increment');
                }

                AuctionBid::query()->create([
                    'auction_lot_id' => (int) $lot->id,
                    'user_id' => (int) $user->id,
                    'amount' => $amount,
                    'status' => 'accepted',
                ]);

                $lot->current_price = $amount;
                $lot->bids_count = (int) $lot->bids_count + 1;
                $lot->last_bid_at = now();
                $lot->winner_user_id = (int) $user->id;
                $lot->save();

                $post->price = $amount;
                $post->save();

                return $lot->fresh(['winner', 'post']);
            });
        } catch (\Throwable $e) {
            $status = method_exists($e, 'getStatusCode') ? (int) $e->getStatusCode() : 422;
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Failed to place bid',
            ], $status > 0 ? $status : 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Bid placed successfully',
        ]);
    }

    public function toggleWatch(Request $request, Post $post)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        if ($post->post_type !== 'auction') return response()->json(['success' => false, 'message' => 'Auction not found'], 404);

        $lot = AuctionLot::query()->where('post_id', (int) $post->id)->first();
        if (!$lot) return response()->json(['success' => false, 'message' => 'Auction lot not found'], 404);

        $existing = AuctionWatcher::query()
            ->where('auction_lot_id', (int) $lot->id)
            ->where('user_id', (int) $user->id)
            ->first();

        $watched = false;
        if ($existing) {
            $existing->delete();
        } else {
            AuctionWatcher::query()->create([
                'auction_lot_id' => (int) $lot->id,
                'user_id' => (int) $user->id,
            ]);
            $watched = true;
        }

        return response()->json([
            'success' => true,
            'watched' => $watched,
        ]);
    }

    public function statistics(Request $request, Post $post)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        if ($post->post_type !== 'auction') return response()->json(['success' => false, 'message' => 'Auction not found'], 404);
        if ((int) $post->user_id !== (int) $user->id) return response()->json(['success' => false, 'message' => 'Forbidden'], 403);

        $lot = AuctionLot::query()
            ->withCount('watchers')
            ->where('post_id', (int) $post->id)
            ->first();
        if (!$lot) return response()->json(['success' => false, 'message' => 'Auction lot not found'], 404);

        $bids = AuctionBid::query()
            ->where('auction_lot_id', (int) $lot->id)
            ->latest()
            ->take(25)
            ->with('user')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => (int) $post->id,
                'title' => $post->title,
                'start_price' => (float) $lot->start_price,
                'current_price' => (float) $lot->current_price,
                'min_increment' => (float) $lot->min_increment,
                'reserve_price' => $lot->reserve_price !== null ? (float) $lot->reserve_price : null,
                'status' => $lot->status,
                'start_at' => $lot->start_at,
                'end_at' => $lot->end_at,
                'bids_count' => (int) $lot->bids_count,
                'watchers_count' => (int) $lot->watchers_count,
                'winner_user_id' => $lot->winner_user_id ? (int) $lot->winner_user_id : null,
                'recent_bids' => $bids,
            ],
        ]);
    }

    public function myAuctions(Request $request)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);

        $query = AuctionLot::query()
            ->with(['post.section', 'post.category', 'post.postImages'])
            ->whereHas('post', function ($q) use ($user) {
                $q->where('post_type', 'auction')->where('user_id', (int) $user->id);
            })
            ->latest();

        $perPage = max(1, (int) $request->get('per_page', 15));
        return response()->json($query->paginate($perPage));
    }

    private function decodeAttributes($attributesRaw): array
    {
        if (is_array($attributesRaw)) return $attributesRaw;
        if (is_string($attributesRaw) && trim($attributesRaw) !== '') {
            $decoded = json_decode($attributesRaw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }

    private function savePostData(int $postId, int $userId, array $validated, array $attributes, bool $isCreate): void
    {
        $attrCollect = collect();
        foreach ($attributes as $attribute) {
            $attributeId = (int) ($attribute['attributeId'] ?? 0);
            if ($attributeId <= 0) continue;

            $item = [];
            $attr = Attribute::select('id', 'name', 'slug')->find($attributeId);
            if (!$attr) continue;
            $item['attribute'] = $attr->toArray();

            $optionId = $attribute['optionId'] ?? null;
            if (is_array($optionId)) {
                $options = AttributeOption::select('id', 'name', 'attribute_id', 'slug')
                    ->whereIn('id', array_map('intval', $optionId))
                    ->get();
                $item['options'] = $options->toArray();
            } elseif ($optionId !== null) {
                $option = AttributeOption::select('id', 'name', 'attribute_id', 'slug')
                    ->find((int) $optionId);
                if ($option) {
                    $item['option'] = $option->toArray();
                }
            }
            $attrCollect->push($item);
        }

        $payload = [
            'post_id' => $postId,
            'user_id' => $userId,
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'price' => isset($validated['start_price']) ? (float) $validated['start_price'] : null,
            'country_id' => (int) ($validated['country_id'] ?? 0),
            'city_id' => (int) ($validated['city_id'] ?? 0),
            'section_id' => (int) ($validated['section_id'] ?? 0),
            'category_id' => (int) ($validated['category_id'] ?? 0),
            'attributes' => $attributes,
            'attributes_and_options' => $attrCollect->toArray(),
            'post_type' => 'auction',
        ];

        if ($isCreate) {
            PostData::query()->create($payload);
        } else {
            PostData::query()->updateOrCreate(
                ['post_id' => $postId],
                $payload
            );
        }
    }

    private function storeImages(Request $request, Post $post): void
    {
        if (!$request->hasFile('images')) return;

        foreach ($request->file('images') as $image) {
            if (!$image || !$image->isValid()) {
                continue;
            }

            $prefix = 'auction_' . Str::random(12);
            $extension = $image->getClientOriginalExtension();
            $fileName = 'auctions/' . $post->id . '/' . $prefix . '.' . $extension;
            $path = 'upload/photos/' . date('Y') . '/' . date('m') . '/' . $fileName;

            Storage::disk('s3')->put(
                $path,
                fopen($image->getRealPath(), 'r'),
                [
                    'visibility' => 'public',
                    'ContentType' => $image->getMimeType(),
                    'ContentDisposition' => 'inline',
                ]
            );

            PostImages::query()->create([
                'post_id' => (int) $post->id,
                'image' => $path,
            ]);
        }
    }
}

