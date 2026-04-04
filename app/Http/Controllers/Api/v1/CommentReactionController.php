<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentReaction;
use Illuminate\Http\Request;

class CommentReactionController extends Controller
{
    /**
     * Toggle a reaction on a comment (auth required).
     * POST /api/comments/{comment}/reactions
     * body: { type: "like" }
     */
    public function toggle(Request $request, Comment $comment)
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:like'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $type = $validated['type'];

        $existing = CommentReaction::query()
            ->where('comment_id', (int) $comment->id)
            ->where('user_id', (int) $user->id)
            ->where('type', $type)
            ->first();

        $toggledOn = false;
        if ($existing) {
            $existing->delete();
        } else {
            CommentReaction::create([
                'comment_id' => (int) $comment->id,
                'post_id' => (int) $comment->post_id,
                'user_id' => (int) $user->id,
                'type' => $type,
            ]);
            $toggledOn = true;
        }

        $likesCount = CommentReaction::query()
            ->where('comment_id', (int) $comment->id)
            ->where('type', 'like')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'comment_id' => $comment->id,
                'type' => $type,
                'toggled_on' => $toggledOn,
                'likes_count' => $likesCount,
            ],
        ]);
    }

    /**
     * Get reaction summary for a comment.
     * GET /api/comments/{comment}/reactions
     */
    public function summary(Request $request, Comment $comment)
    {
        $likesCount = CommentReaction::query()
            ->where('comment_id', (int) $comment->id)
            ->where('type', 'like')
            ->count();

        $likedByMe = false;
        if ($request->user()) {
            $likedByMe = CommentReaction::query()
                ->where('comment_id', (int) $comment->id)
                ->where('user_id', (int) $request->user()->id)
                ->where('type', 'like')
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'comment_id' => $comment->id,
                'likes_count' => $likesCount,
                'liked_by_me' => $likedByMe,
            ],
        ]);
    }
}


