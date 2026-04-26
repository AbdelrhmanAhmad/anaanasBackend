<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\ChatReport;
use App\Models\Message;
use App\Models\Post;
use App\Models\PostEvent;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\RealtimeBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Get or create a chat for a post between the authenticated user and the post owner.
     */
    public function getOrCreate(Request $request, int $postId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $postOwnerId = (int) $post->user_id;
        $currentUserId = (int) $user->id;

        if ($postOwnerId === $currentUserId) {
            return response()->json(['success' => false, 'message' => 'Cannot chat with yourself'], 400);
        }

        $user1Id = min($currentUserId, $postOwnerId);
        $user2Id = max($currentUserId, $postOwnerId);

        $chat = Chat::where('post_id', $postId)
            ->where('user1_id', $user1Id)
            ->where('user2_id', $user2Id)
            ->first();

        if (!$chat) {
            try {
                $chat = Chat::create([
                    'post_id' => $postId,
                    'user1_id' => $user1Id,
                    'user2_id' => $user2Id,
                    'unread_count_user1' => 0,
                    'unread_count_user2' => 0,
                    'archived_by_user1' => false,
                    'archived_by_user2' => false,
                    'deleted_by_user1' => false,
                    'deleted_by_user2' => false,
                    'closed' => false,
                    'blocked_by_user1' => false,
                    'blocked_by_user2' => false,
                    'reports_count' => 0,
                ]);
            } catch (\Throwable $e) {
                \Log::error('Failed to create chat: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create chat: ' . $e->getMessage(),
                ], 500);
            }
        } elseif ($chat->deleted_by_user1 || $chat->deleted_by_user2) {
            // Re-opening a previously soft-deleted thread on either side.
            if ($chat->user1_id == $currentUserId) {
                $chat->update(['deleted_by_user1' => false]);
            } elseif ($chat->user2_id == $currentUserId) {
                $chat->update(['deleted_by_user2' => false]);
            }
        }

        // Analytics: chat opened (best-effort)
        try {
            PostEvent::create([
                'post_id' => (int) $postId,
                'user_id' => (int) $currentUserId,
                'event' => 'post_chat_open',
                'meta' => [
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'chat_id' => (string) $this->chatId($chat),
                    'target_user_id' => (int) $postOwnerId,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore analytics failures
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeChat($chat, $currentUserId),
        ]);
    }

    /**
     * List all chats for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $userId = (int) $user->id;
        $perPage = (int) $request->get('per_page', 20);
        $page = (int) $request->get('page', 1);

        $chats = Chat::where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)->where('deleted_by_user1', false);
            })
            ->orWhere(function ($query) use ($userId) {
                $query->where('user2_id', $userId)->where('deleted_by_user2', false);
            })
            ->orderBy('last_message_at', 'desc')
            ->skip(max(0, ($page - 1) * $perPage))
            ->take($perPage)
            ->get();

        $data = $chats->map(fn ($chat) => $this->serializeChat($chat, $userId, /* withLastMessage */ true))->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Show a specific chat.
     */
    public function show(Request $request, string $chatId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $chat = Chat::find($chatId);
        if (!$chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found'], 404);
        }

        $userId = (int) $user->id;
        if (!$chat->isParticipant($userId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeChat($chat, $userId, true),
        ]);
    }

    /**
     * Mark chat as read (reset my unread counter).
     */
    public function markAsRead(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        $chat->resetUnreadForUser($userId);

        // Notify the other side that we caught up — useful for double-check ticks.
        try {
            RealtimeBroadcaster::publish('chat:' . $this->chatId($chat), 'chat.read', [
                'chat_id' => (string) $this->chatId($chat),
                'user_id' => $userId,
                'read_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }

        return response()->json(['success' => true, 'message' => 'Chat marked as read']);
    }

    /**
     * Soft-delete the chat for the current user (the other side keeps their copy).
     */
    public function delete(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        if ($chat->user1_id == $userId) {
            $chat->update(['deleted_by_user1' => true]);
        } else {
            $chat->update(['deleted_by_user2' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Chat deleted']);
    }

    /**
     * Clear my view of the conversation history (set per-user cleared_at cutoff).
     * The counterparty still sees everything.
     */
    public function clear(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        $chat->clearForUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'Chat history cleared',
            'data' => $this->serializeChat($chat->refresh(), $userId, true),
        ]);
    }

    /**
     * Close a conversation (read-only for both sides).
     */
    public function close(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        $chat->close($userId);

        $payload = [
            'chat_id' => (string) $this->chatId($chat),
            'closed_by' => $userId,
            'closed_at' => now()->toISOString(),
        ];
        try {
            RealtimeBroadcaster::publish('chat:' . $this->chatId($chat), 'chat.closed', $payload);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Chat closed',
            'data' => $this->serializeChat($chat->refresh(), $userId, true),
        ]);
    }

    public function reopen(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        $chat->reopen();

        try {
            RealtimeBroadcaster::publish('chat:' . $this->chatId($chat), 'chat.reopened', [
                'chat_id' => (string) $this->chatId($chat),
                'reopened_by' => $userId,
                'reopened_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Chat reopened',
            'data' => $this->serializeChat($chat->refresh(), $userId, true),
        ]);
    }

    /**
     * Block the other participant — they can no longer send new messages.
     */
    public function block(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        $chat->blockByUser($userId);

        try {
            RealtimeBroadcaster::publish('chat:' . $this->chatId($chat), 'chat.blocked', [
                'chat_id' => (string) $this->chatId($chat),
                'blocked_by' => $userId,
                'blocked_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'User blocked',
            'data' => $this->serializeChat($chat->refresh(), $userId, true),
        ]);
    }

    public function unblock(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        $chat->unblockByUser($userId);

        try {
            RealtimeBroadcaster::publish('chat:' . $this->chatId($chat), 'chat.unblocked', [
                'chat_id' => (string) $this->chatId($chat),
                'unblocked_by' => $userId,
                'unblocked_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'User unblocked',
            'data' => $this->serializeChat($chat->refresh(), $userId, true),
        ]);
    }

    /**
     * Report this conversation for admin review.
     */
    public function report(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
            'category' => 'sometimes|string|in:spam,harassment,scam,inappropriate,other',
            'description' => 'sometimes|string|max:5000',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $reportedUserId = (int) $chat->getOtherUserId($userId);
        $latestMessage = Message::where('chat_id', (string) $this->chatId($chat))
            ->orderBy('created_at', 'desc')
            ->first();

        $snapshot = $latestMessage ? [
            'id' => (string) ($latestMessage->_id ?? $latestMessage->id),
            'sender_id' => (int) $latestMessage->sender_id,
            'body' => mb_substr((string) $latestMessage->body, 0, 600),
            'created_at' => $latestMessage->created_at?->toISOString(),
        ] : null;

        $report = ChatReport::create([
            'chat_id' => (string) $this->chatId($chat),
            'post_id' => (int) $chat->post_id,
            'reporter_id' => $userId,
            'reported_user_id' => $reportedUserId,
            'reason' => (string) $request->input('reason'),
            'category' => (string) $request->input('category', 'other'),
            'description' => (string) $request->input('description', ''),
            'status' => ChatReport::STATUS_PENDING,
            'snapshot' => $snapshot,
        ]);

        $chat->incrementReports();

        // Optionally fan the report out to all admins as an in-app notification.
        try {
            $reporterName = $request->user()->name ?? 'مستخدم';
            $admins = \App\Models\Admin::query()->limit(50)->get(['id']);
            foreach ($admins as $admin) {
                UserNotification::create([
                    'user_id' => (int) $admin->id,
                    'type' => 'chat.report',
                    'title_ar' => 'بلاغ جديد على محادثة',
                    'title_en' => 'New chat report',
                    'body_ar' => mb_substr('بلّغ ' . $reporterName . ' عن محادثة', 0, 200),
                    'body_en' => mb_substr($reporterName . ' reported a conversation', 0, 200),
                    'url' => '/admin/chat-reports/' . (string) ($report->_id ?? $report->id),
                    'data' => [
                        'chat_id' => (string) $this->chatId($chat),
                        'report_id' => (string) ($report->_id ?? $report->id),
                        'reporter_id' => $userId,
                        'reported_user_id' => $reportedUserId,
                    ],
                ]);
            }
        } catch (\Throwable $e) {
            // best-effort
        }

        return response()->json([
            'success' => true,
            'message' => 'Report submitted',
            'data' => [
                'report_id' => (string) ($report->_id ?? $report->id),
            ],
        ], 201);
    }

    /**
     * Set the typing flag and broadcast it (best-effort) to the chat channel.
     */
    public function typing(Request $request, string $chatId): JsonResponse
    {
        $chat = $this->guardChat($chatId, $userId);
        if ($chat instanceof JsonResponse) return $chat;

        $chat->setTyping($userId);

        try {
            RealtimeBroadcaster::publish('chat:' . $this->chatId($chat), 'chat.typing', [
                'chat_id' => (string) $this->chatId($chat),
                'user_id' => $userId,
                'at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json(['success' => true]);
    }

    /* ------------------------------------------------------------------
       Internal helpers
       ------------------------------------------------------------------ */

    private function guardChat(string $chatId, ?int &$userId)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        $userId = (int) $user->id;

        $chat = Chat::find($chatId);
        if (!$chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found'], 404);
        }
        if (!$chat->isParticipant($userId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        return $chat;
    }

    private function chatId(Chat $chat): string
    {
        return (string) ($chat->_id ?? $chat->id ?? $chat->getKey());
    }

    /**
     * Build the canonical chat payload that the frontend consumes.
     */
    private function serializeChat(Chat $chat, int $currentUserId, bool $withLastMessage = false): array
    {
        $otherUserId = $chat->getOtherUserId($currentUserId);
        $otherUser = $otherUserId ? User::find($otherUserId) : null;
        $post = Post::find($chat->post_id);
        $postImage = null;
        if ($post) {
            $first = $post->postImages()->orderBy('id', 'asc')->first();
            if ($first) {
                try { $postImage = $first->image_full_url; } catch (\Throwable $e) { $postImage = null; }
            }
            if (!$postImage && !empty($post->main_image)) {
                try {
                    $postImage = preg_match('/^https?:\/\//i', (string) $post->main_image)
                        ? (string) $post->main_image
                        : \Illuminate\Support\Facades\Storage::disk('s3')->url((string) $post->main_image);
                } catch (\Throwable $e) {
                    $postImage = null;
                }
            }
        }

        $lastMessage = null;
        if ($withLastMessage && $chat->last_message_id) {
            $msg = Message::find($chat->last_message_id);
            if ($msg) {
                $lastMessage = [
                    'id' => (string) ($msg->_id ?? $msg->id),
                    'body' => $msg->body,
                    'sender_id' => $msg->sender_id,
                    'sent_at' => $msg->created_at?->toISOString(),
                ];
            }
        }

        return [
            'id' => $this->chatId($chat),
            'post_id' => (int) $chat->post_id,
            'post' => $post ? [
                'id' => (int) $post->id,
                'title' => (string) $post->title,
                'image' => $postImage,
                'price' => $post->price,
            ] : null,
            'other_user' => $otherUser ? [
                'id' => (int) $otherUser->id,
                'name' => (string) $otherUser->name,
                'username' => $otherUser->username ?? null,
                'avatar' => $otherUser->avatar_url,
            ] : null,
            'unread_count' => $chat->getUnreadCountForUser($currentUserId),
            'last_message' => $lastMessage,
            'last_message_at' => $chat->last_message_at?->toISOString(),
            'created_at' => $chat->created_at?->toISOString(),
            // moderation flags (per-user view)
            'is_closed' => (bool) $chat->closed,
            'closed_by' => $chat->closed_by ? (int) $chat->closed_by : null,
            'closed_at' => $chat->closed_at?->toISOString(),
            'i_blocked_them' => $chat->isBlockedByUser($currentUserId),
            'they_blocked_me' => $otherUserId ? $chat->isBlockedByUser((int) $otherUserId) : false,
            'is_blocked' => $chat->isBlockedForUser($currentUserId),
            'cleared_at' => $chat->getClearedAtForUser($currentUserId)?->format(\DateTimeInterface::ATOM),
            'reports_count' => (int) ($chat->reports_count ?? 0),
        ];
    }
}
