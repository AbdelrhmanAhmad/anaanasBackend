<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $land = (string) ($request->get('land') ?? app()->getLocale());
        $isAr = str_starts_with($land, 'ar');
        $perPage = max(1, min(50, (int) $request->get('per_page', 20)));

        $rows = UserNotification::query()
            ->where('user_id', (int) $user->id)
            ->orderByDesc('id')
            ->paginate($perPage);

        $payload = $rows->toArray();
        $payload['data'] = collect($rows->items())->map(function (UserNotification $n) use ($isAr) {
            return [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $isAr ? $n->title_ar : ($n->title_en ?: $n->title_ar),
                'body' => $isAr ? ($n->body_ar ?: '') : ($n->body_en ?: ($n->body_ar ?: '')),
                'url' => $n->url,
                'is_read' => (bool) $n->is_read,
                'created_at' => optional($n->created_at)?->toISOString(),
                'data' => $n->data ?? [],
            ];
        })->values()->all();

        $payload['unread_count'] = UserNotification::query()
            ->where('user_id', (int) $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success' => true,
            'data' => $payload['data'],
            'meta' => [
                'current_page' => $payload['current_page'] ?? 1,
                'next_page_url' => $payload['next_page_url'] ?? null,
                'prev_page_url' => $payload['prev_page_url'] ?? null,
            ],
            'unread_count' => (int) ($payload['unread_count'] ?? 0),
        ]);
    }

    public function markRead(Request $request, UserNotification $notification)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        if ((int) $notification->user_id !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if (!$notification->is_read) {
            $notification->is_read = true;
            $notification->read_at = now();
            $notification->save();
        }

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        UserNotification::query()
            ->where('user_id', (int) $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }
}
