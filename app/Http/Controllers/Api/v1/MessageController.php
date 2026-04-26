<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\RealtimeBroadcaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * List messages for a chat (chronological order, paginated).
     */
    public function index(Request $request, string $chatId): JsonResponse
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

        $perPage = (int) $request->get('per_page', 50);
        $page = (int) $request->get('page', 1);
        $beforeId = $request->get('before_id');
        $afterAt = $request->get('after'); // ISO timestamp — used by polling clients

        $query = Message::where('chat_id', $chatId)->orderBy('created_at', 'desc');

        // Apply per-user clear cutoff so users only see their own slice.
        $clearedAt = $chat->getClearedAtForUser($userId);
        if ($clearedAt) {
            $query->where('created_at', '>', $clearedAt);
        }

        if ($beforeId) {
            $beforeMessage = Message::find($beforeId);
            if ($beforeMessage) {
                $query->where('created_at', '<', $beforeMessage->created_at);
            }
        }
        if ($afterAt) {
            try {
                $cursor = new \Carbon\Carbon($afterAt);
                $query->where('created_at', '>', $cursor);
            } catch (\Throwable $e) {
                // ignore bad input
            }
        }

        $messages = $query
            ->skip(max(0, ($page - 1) * $perPage))
            ->take($perPage)
            ->get()
            ->reverse(); // chronological asc

        // Mark inbound messages as read for the current user
        foreach ($messages as $message) {
            if ($message->sender_id != $userId && !$message->isReadBy($userId)) {
                $message->markAsReadBy($userId);
            }
        }
        $chat->resetUnreadForUser($userId);

        return response()->json([
            'success' => true,
            'data' => $messages->values()->map(fn ($m) => $this->serializeMessage($m, $userId))->all(),
        ]);
    }

    /**
     * Send a message.
     */
    public function store(Request $request, string $chatId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:5000',
            'type' => 'sometimes|in:text,image,file',
            'file_url' => 'sometimes|nullable|url',
            'client_id' => 'sometimes|nullable|string|max:64', // for optimistic-update reconciliation
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $chat = Chat::find($chatId);
        if (!$chat) {
            return response()->json(['success' => false, 'message' => 'Chat not found'], 404);
        }

        $userId = (int) $user->id;
        if (!$chat->isParticipant($userId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        if ($chat->closed) {
            return response()->json([
                'success' => false,
                'message' => 'This conversation is closed',
            ], 423);
        }
        if ($chat->isBlockedForUser($userId)) {
            return response()->json([
                'success' => false,
                'message' => 'You can no longer send messages in this conversation',
            ], 423);
        }

        $message = Message::create([
            'chat_id' => $chatId,
            'sender_id' => $userId,
            'body' => $request->body,
            'type' => $request->get('type', 'text'),
            'file_url' => $request->get('file_url'),
            'read_by' => [$userId], // sender has read their own message
        ]);

        $messageId = (string) ($message->_id ?? $message->id ?? $message->getKey());
        $otherUserId = (int) $chat->getOtherUserId($userId);

        $chat->update([
            'last_message_id' => $messageId,
            'last_message_at' => $message->created_at,
        ]);
        if ($otherUserId) {
            $chat->incrementUnreadForUser($otherUserId);
        }

        // Best-effort: in-app notification + websocket fan-out
        if ($otherUserId) {
            try {
                UserNotification::create([
                    'user_id' => $otherUserId,
                    'type' => 'chat.message',
                    'title_ar' => 'رسالة جديدة',
                    'title_en' => 'New message',
                    'body_ar' => mb_substr((string) $request->body, 0, 160),
                    'body_en' => mb_substr((string) $request->body, 0, 160),
                    'url' => '/messaging?chat=' . rawurlencode($chatId),
                    'data' => [
                        'chat_id' => (string) $chatId,
                        'post_id' => (int) $chat->post_id,
                        'sender_id' => (int) $userId,
                        'message_id' => $messageId,
                    ],
                ]);
            } catch (\Throwable $e) {
                // best-effort
            }
        }

        $serialized = $this->serializeMessage($message, $userId, $request->input('client_id'));

        try {
            // Fan-out is identical for every subscriber; strip is_mine (it is only valid for the HTTP sender).
            $forChatChannel = $serialized;
            unset($forChatChannel['is_mine']);

            RealtimeBroadcaster::publish('chat:' . $chatId, 'chat.message.created', [
                'chat_id' => $chatId,
                'message' => $forChatChannel,
            ]);
            // Also broadcast to a per-user inbox channel so the chats list and
            // unread badge can refresh without polling.
            if ($otherUserId) {
                RealtimeBroadcaster::publish('user:' . $otherUserId, 'chat.inbox.updated', [
                    'chat_id' => $chatId,
                    'last_message' => [
                        'id' => $serialized['id'],
                        'body' => $serialized['body'],
                        'sender_id' => $serialized['sender_id'],
                        'sent_at' => $serialized['created_at'],
                    ],
                    'unread_count' => $chat->fresh()->getUnreadCountForUser($otherUserId),
                ]);
            }
            RealtimeBroadcaster::publish('user:' . $userId, 'chat.inbox.updated', [
                'chat_id' => $chatId,
                'last_message' => [
                    'id' => $serialized['id'],
                    'body' => $serialized['body'],
                    'sender_id' => $serialized['sender_id'],
                    'sent_at' => $serialized['created_at'],
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore realtime errors
        }

        return response()->json([
            'success' => true,
            'data' => $serialized,
        ], 201);
    }

    /**
     * Mark all unread inbound messages as read.
     */
    public function markAsRead(Request $request, string $chatId): JsonResponse
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

        $unreadMessages = Message::where('chat_id', $chatId)
            ->where('sender_id', '!=', $userId)
            ->get();
        foreach ($unreadMessages as $message) {
            $message->markAsReadBy($userId);
        }
        $chat->resetUnreadForUser($userId);

        try {
            RealtimeBroadcaster::publish('chat:' . $chatId, 'chat.read', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'read_at' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {}

        return response()->json(['success' => true]);
    }

    /**
     * Build the canonical message payload (used by both index & store).
     */
    private function serializeMessage(Message $message, int $currentUserId, ?string $clientId = null): array
    {
        $messageId = (string) ($message->_id ?? $message->id ?? $message->getKey());
        $sender = User::find($message->sender_id);

        return [
            'id' => $messageId,
            'client_id' => $clientId,
            'chat_id' => (string) $message->chat_id,
            'sender_id' => (int) $message->sender_id,
            'sender' => $sender ? [
                'id' => (int) $sender->id,
                'name' => (string) $sender->name,
                'username' => $sender->username ?? null,
                'avatar' => $sender->avatar_url,
            ] : null,
            'body' => $message->body,
            'type' => $message->type ?? 'text',
            'file_url' => $message->file_url,
            'is_read' => $message->isReadBy($currentUserId),
            'is_mine' => (int) $message->sender_id === $currentUserId,
            'read_at' => $message->read_at?->toISOString(),
            'read_by' => array_values(array_map('intval', $message->read_by ?? [])),
            'created_at' => $message->created_at?->toISOString(),
        ];
    }
}
