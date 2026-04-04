<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostEvent;
use App\Models\PostReaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostReactionController extends Controller
{
    /**
     * Toggle a reaction on a post (auth required).
     * POST /api/posts/{post}/reactions
     * body: { type: "like" }
     */
    public function toggle(Request $request, Post $post)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:like'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $type = $validated['type'];

        $toggledOn = false;
        $likesCount = (int) ($post->likes_count ?? 0);

        DB::transaction(function () use ($post, $user, $type, &$toggledOn, &$likesCount) {
            $existing = PostReaction::query()
                ->where('post_id', (int) $post->id)
                ->where('user_id', (int) $user->id)
                ->where('type', $type)
                ->first();

            $hasLikesColumn = Schema::hasColumn('posts', 'likes_count');

            if ($existing) {
                $existing->delete();
                $toggledOn = false;

                if ($hasLikesColumn) {
                    // Avoid going negative
                    $post->refresh();
                    if ((int) $post->likes_count > 0) {
                        $post->decrement('likes_count');
                    }
                }

                PostEvent::create([
                    'post_id' => (int) $post->id,
                    'user_id' => (int) $user->id,
                    'event' => 'post_unlike',
                    'meta' => ['type' => $type],
                ]);
            } else {
                PostReaction::create([
                    'post_id' => (int) $post->id,
                    'user_id' => (int) $user->id,
                    'type' => $type,
                ]);
                $toggledOn = true;

                if ($hasLikesColumn) {
                    $post->increment('likes_count');
                }

                PostEvent::create([
                    'post_id' => (int) $post->id,
                    'user_id' => (int) $user->id,
                    'event' => 'post_like',
                    'meta' => ['type' => $type],
                ]);
            }

            $post->refresh();
            $likesCount = (int) ($post->likes_count ?? 0);
        });

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'type' => $type,
                'toggled_on' => $toggledOn,
                'likes_count' => $likesCount,
            ],
        ]);
    }

    /**
     * Summary (auth optional).
     * GET /api/posts/{post}/reactions
     */
    public function summary(Request $request, Post $post)
    {
        $likedByMe = false;
        if ($request->user()) {
            $likedByMe = PostReaction::query()
                ->where('post_id', (int) $post->id)
                ->where('user_id', (int) $request->user()->id)
                ->where('type', 'like')
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'likes_count' => (int) ($post->likes_count ?? 0),
                'liked_by_me' => $likedByMe,
            ],
        ]);
    }
}


