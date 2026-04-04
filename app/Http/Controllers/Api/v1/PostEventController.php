<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostEvent;
use Illuminate\Http\Request;

class PostEventController extends Controller
{
    /**
     * Store analytics events for a post (MongoDB).
     * POST /api/posts/{post}/events
     */
    public function store(Request $request, Post $post)
    {
        $validated = $request->validate([
            'event' => ['required', 'string', 'in:post_share,post_call,post_view,post_chat_open,post_impression,post_like,post_unlike,post_comment'],
            'meta' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        $meta = $validated['meta'] ?? [];
        $meta['ip'] = $request->ip();
        $meta['user_agent'] = (string) $request->userAgent();

        $doc = PostEvent::create([
            'post_id' => (int) $post->id,
            'user_id' => $user?->id ? (int) $user->id : null,
            'event' => $validated['event'],
            'meta' => $meta,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) ($doc->_id ?? $doc->id ?? ''),
            ],
        ], 201);
    }
}


