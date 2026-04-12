<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Post;
use App\Models\PostEvent;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * List post comments (top-level only).
     * GET /api/posts/{post}/comments?page=1
     */
    public function index(Request $request, Post $post)
    {
        $perPage = (int) ($request->integer('per_page') ?: 10);
        $perPage = max(1, min(50, $perPage));

        $comments = Comment::query()
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with('user')
            ->withCount('children')
            ->latest()
            ->simplePaginate($perPage);

        $commentsCollection = collect($comments->items());
        $commentIds = $commentsCollection->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $likesCountsByCommentId = [];
        $likedByMeSet = [];

        if (count($commentIds) > 0) {
            // counts aggregation in MongoDB
            $agg = CommentReaction::raw(function ($collection) use ($commentIds) {
                return $collection->aggregate([
                    ['$match' => ['comment_id' => ['$in' => $commentIds], 'type' => 'like']],
                    ['$group' => ['_id' => '$comment_id', 'count' => ['$sum' => 1]]],
                ]);
            });

            foreach ($agg as $row) {
                $likesCountsByCommentId[(int) $row->_id] = (int) $row->count;
            }

            if ($request->user()) {
                $likedDocs = CommentReaction::query()
                    ->whereIn('comment_id', $commentIds)
                    ->where('user_id', (int) $request->user()->id)
                    ->where('type', 'like')
                    ->get(['comment_id']);

                foreach ($likedDocs as $doc) {
                    $likedByMeSet[(int) $doc->comment_id] = true;
                }
            }
        }

        $data = $commentsCollection
            ->map(function (Comment $c) use ($request, $likesCountsByCommentId, $likedByMeSet) {
                $arr = (new CommentResource($c))->toArray($request);
                $arr['likes_count'] = $likesCountsByCommentId[(int) $c->id] ?? 0;
                $arr['liked_by_me'] = (bool) ($likedByMeSet[(int) $c->id] ?? false);
                $arr['replies_count'] = (int) ($c->children_count ?? 0);
                return $arr;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $comments->currentPage(),
                'per_page' => $comments->perPage(),
                'next_page_url' => $comments->nextPageUrl(),
                'prev_page_url' => $comments->previousPageUrl(),
            ],
        ]);
    }

    /**
     * Create a comment (auth required).
     * POST /api/posts/{post}/comments
     */
    public function store(Request $request, Post $post)
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:4000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        if (!empty($validated['parent_id'])) {
            $parent = Comment::query()->find($validated['parent_id']);
            if (!$parent || (int) $parent->post_id !== (int) $post->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid parent comment',
                ], 422);
            }
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => $validated['body'],
        ]);

        $comment->load('user');

        // Analytics: comment created
        try {
            PostEvent::create([
                'post_id' => (int) $post->id,
                'user_id' => (int) $user->id,
                'event' => 'post_comment',
                'meta' => [
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'parent_id' => $validated['parent_id'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore analytics failures
        }

        if ((int) $post->user_id !== (int) $user->id) {
            UserNotification::create([
                'user_id' => (int) $post->user_id,
                'type' => 'post.comment',
                'title_ar' => 'تعليق جديد على إعلانك',
                'title_en' => 'New comment on your post',
                'body_ar' => mb_substr((string) $validated['body'], 0, 180),
                'body_en' => mb_substr((string) $validated['body'], 0, 180),
                'url' => '/post/' . (int) $post->id,
                'data' => [
                    'post_id' => (int) $post->id,
                    'comment_id' => (int) $comment->id,
                    'from_user_id' => (int) $user->id,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge(
                (new CommentResource($comment))->toArray($request),
                [
                    'likes_count' => 0,
                    'liked_by_me' => false,
                    'replies_count' => 0,
                ]
            ),
        ], 201);
    }

    /**
     * List replies for a comment (auth optional).
     * GET /api/comments/{comment}/replies?page=1
     */
    public function replies(Request $request, Comment $comment)
    {
        $perPage = (int) ($request->integer('per_page') ?: 10);
        $perPage = max(1, min(50, $perPage));

        $replies = Comment::query()
            ->where('parent_id', $comment->id)
            ->with('user')
            ->latest()
            ->simplePaginate($perPage);

        $repliesCollection = collect($replies->items());
        $replyIds = $repliesCollection->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $likesCountsByCommentId = [];
        $likedByMeSet = [];

        if (count($replyIds) > 0) {
            $agg = CommentReaction::raw(function ($collection) use ($replyIds) {
                return $collection->aggregate([
                    ['$match' => ['comment_id' => ['$in' => $replyIds], 'type' => 'like']],
                    ['$group' => ['_id' => '$comment_id', 'count' => ['$sum' => 1]]],
                ]);
            });

            foreach ($agg as $row) {
                $likesCountsByCommentId[(int) $row->_id] = (int) $row->count;
            }

            if ($request->user()) {
                $likedDocs = CommentReaction::query()
                    ->whereIn('comment_id', $replyIds)
                    ->where('user_id', (int) $request->user()->id)
                    ->where('type', 'like')
                    ->get(['comment_id']);

                foreach ($likedDocs as $doc) {
                    $likedByMeSet[(int) $doc->comment_id] = true;
                }
            }
        }

        $data = $repliesCollection
            ->map(function (Comment $c) use ($request, $likesCountsByCommentId, $likedByMeSet) {
                $arr = (new CommentResource($c))->toArray($request);
                $arr['likes_count'] = $likesCountsByCommentId[(int) $c->id] ?? 0;
                $arr['liked_by_me'] = (bool) ($likedByMeSet[(int) $c->id] ?? false);
                $arr['replies_count'] = 0;
                return $arr;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $replies->currentPage(),
                'per_page' => $replies->perPage(),
                'next_page_url' => $replies->nextPageUrl(),
                'prev_page_url' => $replies->previousPageUrl(),
            ],
        ]);
    }
}


