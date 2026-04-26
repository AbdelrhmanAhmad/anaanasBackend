<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostEvent;
use App\Models\PostReaction;
use App\Models\UserNotification;
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
            'type' => ['required', 'string', 'in:' . implode(',', PostReaction::allowedTypes())],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $type = $validated['type'];

        $toggledOn = false;
        $likesCount = 0;
        $reactionTypeByMe = null;
        $reactionCounts = [];

        DB::transaction(function () use ($post, $user, $type, &$toggledOn, &$likesCount, &$reactionTypeByMe, &$reactionCounts) {
            $existing = PostReaction::query()
                ->where('post_id', (int) $post->id)
                ->where('user_id', (int) $user->id)
                ->first();

            $hasLikesColumn = Schema::hasColumn('posts', 'likes_count');

            if ($existing && (string) $existing->type === $type) {
                $existing->delete();
                $toggledOn = false;
                $reactionTypeByMe = null;

                PostEvent::create([
                    'post_id' => (int) $post->id,
                    'user_id' => (int) $user->id,
                    'event' => 'post_unlike',
                    'meta' => ['type' => $type],
                ]);
            } elseif ($existing) {
                $existing->update(['type' => $type]);
                $toggledOn = true;
                $reactionTypeByMe = $type;

                PostEvent::create([
                    'post_id' => (int) $post->id,
                    'user_id' => (int) $user->id,
                    'event' => 'post_like',
                    'meta' => [
                        'type' => $type,
                        'mode' => 'switch',
                    ],
                ]);
            } else {
                PostReaction::create([
                    'post_id' => (int) $post->id,
                    'user_id' => (int) $user->id,
                    'type' => $type,
                ]);
                $toggledOn = true;
                $reactionTypeByMe = $type;

                PostEvent::create([
                    'post_id' => (int) $post->id,
                    'user_id' => (int) $user->id,
                    'event' => 'post_like',
                    'meta' => ['type' => $type],
                ]);
            }

            $reactionCountsRaw = PostReaction::query()
                ->where('post_id', (int) $post->id)
                ->get(['type'])
                ->groupBy('type')
                ->map(fn ($group) => count($group))
                ->all();

            $reactionCounts = [];
            foreach (PostReaction::allowedTypes() as $reactionType) {
                $reactionCounts[$reactionType] = (int) ($reactionCountsRaw[$reactionType] ?? 0);
            }

            $likesCount = array_sum($reactionCounts);

            if ($hasLikesColumn) {
                $post->update(['likes_count' => $likesCount]);
            }

            $post->refresh();
        });

        // إشعار صاحب الإعلان عند التفاعل (فقط عند تفعيل التفاعل لمن ليس مالك الإعلان)
        if ($toggledOn && (int) $post->user_id > 0 && (int) $post->user_id !== (int) $user->id) {
            try {
                $emojiByType = [
                    'like' => '👍',
                    'love' => '❤️',
                    'care' => '🥰',
                    'haha' => '😂',
                    'wow' => '😮',
                    'sad' => '😢',
                    'angry' => '😡',
                ];
                $labelArByType = [
                    'like' => 'أعجبه إعلانك',
                    'love' => 'أحبّ إعلانك',
                    'care' => 'أعجبه إعلانك واهتم به',
                    'haha' => 'أضحكه إعلانك',
                    'wow' => 'أدهشه إعلانك',
                    'sad' => 'أحزنه إعلانك',
                    'angry' => 'أغضبه إعلانك',
                ];
                $labelEnByType = [
                    'like' => 'liked your post',
                    'love' => 'loved your post',
                    'care' => 'cares about your post',
                    'haha' => 'reacted Haha to your post',
                    'wow' => 'reacted Wow to your post',
                    'sad' => 'reacted Sad to your post',
                    'angry' => 'reacted Angry to your post',
                ];

                $emoji = $emojiByType[$type] ?? '👍';
                $fromName = (string) ($user->name ?? '');
                $titleAr = trim(($fromName !== '' ? $fromName : 'مستخدم') . ' ' . ($labelArByType[$type] ?? 'تفاعل مع إعلانك'));
                $titleEn = trim(($fromName !== '' ? $fromName : 'Someone') . ' ' . ($labelEnByType[$type] ?? 'reacted to your post'));
                $postTitle = (string) ($post->title ?? '');

                UserNotification::create([
                    'user_id' => (int) $post->user_id,
                    'type' => 'post.reaction',
                    'title_ar' => $emoji . ' ' . mb_substr($titleAr, 0, 220),
                    'title_en' => $emoji . ' ' . mb_substr($titleEn, 0, 220),
                    'body_ar' => $postTitle !== '' ? mb_substr($postTitle, 0, 180) : null,
                    'body_en' => $postTitle !== '' ? mb_substr($postTitle, 0, 180) : null,
                    'url' => '/post/' . (int) $post->id,
                    'data' => [
                        'post_id' => (int) $post->id,
                        'reaction_type' => $type,
                        'from_user_id' => (int) $user->id,
                        'from_user_name' => $fromName,
                    ],
                ]);
            } catch (\Throwable $e) {
                // ignore notification errors so the reaction call always succeeds
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'type' => $type,
                'toggled_on' => $toggledOn,
                'likes_count' => $likesCount,
                'reaction_type_by_me' => $reactionTypeByMe,
                'reaction_counts' => $reactionCounts,
            ],
        ]);
    }

    /**
     * Summary (auth optional).
     * GET /api/posts/{post}/reactions
     */
    public function summary(Request $request, Post $post)
    {
        $reactionTypeByMe = null;
        if ($request->user()) {
            $myReaction = PostReaction::query()
                ->where('post_id', (int) $post->id)
                ->where('user_id', (int) $request->user()->id)
                ->first(['type']);
            $reactionTypeByMe = $myReaction?->type ? (string) $myReaction->type : null;
        }

        $reactionCountsRaw = PostReaction::query()
            ->where('post_id', (int) $post->id)
            ->get(['type'])
            ->groupBy('type')
            ->map(fn ($group) => count($group))
            ->all();

        $reactionCounts = [];
        foreach (PostReaction::allowedTypes() as $reactionType) {
            $reactionCounts[$reactionType] = (int) ($reactionCountsRaw[$reactionType] ?? 0);
        }

        $totalReactions = array_sum($reactionCounts);

        return response()->json([
            'success' => true,
            'data' => [
                'post_id' => $post->id,
                'likes_count' => $totalReactions,
                'liked_by_me' => $reactionTypeByMe !== null,
                'reaction_type_by_me' => $reactionTypeByMe,
                'reaction_counts' => $reactionCounts,
            ],
        ]);
    }
}


