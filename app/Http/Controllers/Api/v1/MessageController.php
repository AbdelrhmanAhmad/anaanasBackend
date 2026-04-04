<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    /**
     * Get messages for a chat
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

        $userId = $user->id;
        if ($chat->user1_id != $userId && $chat->user2_id != $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $perPage = $request->get('per_page', 50);
        $page = $request->get('page', 1);
        $beforeId = $request->get('before_id'); // For pagination: get messages before this ID

        $query = Message::where('chat_id', $chatId)
            ->orderBy('created_at', 'desc');

        if ($beforeId) {
            $beforeMessage = Message::find($beforeId);
            if ($beforeMessage) {
                $query->where('created_at', '<', $beforeMessage->created_at);
            }
        }

        $messages = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->reverse(); // Reverse to get chronological order

        // Mark messages as read for the current user
        foreach ($messages as $message) {
            if ($message->sender_id != $userId) {
                $message->markAsReadBy($userId);
            }
        }

        // Reset unread count for this chat
        $chat->resetUnreadForUser($userId);

        $messagesData = [];
        foreach ($messages as $message) {
            $sender = \App\Models\User::find($message->sender_id);
            $messageId = $message->_id ?? $message->id ?? $message->getKey();
            $messagesData[] = [
                'id' => (string) $messageId,
                'chat_id' => (string) $message->chat_id,
                'sender_id' => $message->sender_id,
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'avatar' => $sender->avatar ?? null,
                ] : null,
                'body' => $message->body,
                'type' => $message->type ?? 'text',
                'file_url' => $message->file_url,
                'is_read' => $message->isReadBy($userId),
                'read_at' => $message->read_at?->toISOString(),
                'created_at' => $message->created_at?->toISOString(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $messagesData,
            'has_more' => $query->count() > ($page * $perPage),
        ]);
    }

    /**
     * Send a new message
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

        $userId = $user->id;
        if ($chat->user1_id != $userId && $chat->user2_id != $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Create message
        $message = Message::create([
            'chat_id' => $chatId,
            'sender_id' => $userId,
            'body' => $request->body,
            'type' => $request->get('type', 'text'),
            'file_url' => $request->get('file_url'),
            'read_by' => [$userId], // Sender has read their own message
        ]);

        // Update chat's last message
        $otherUserId = $chat->getOtherUserId($userId);
        $messageId = $message->_id ?? $message->id ?? $message->getKey();
        $chat->update([
            'last_message_id' => (string) $messageId,
            'last_message_at' => $message->created_at,
        ]);

        // Increment unread count for the other user
        $chat->incrementUnreadForUser($otherUserId);

        $sender = \App\Models\User::find($userId);
        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $messageId,
                'chat_id' => (string) $message->chat_id,
                'sender_id' => $message->sender_id,
                'sender' => $sender ? [
                    'id' => $sender->id,
                    'name' => $sender->name,
                    'avatar' => $sender->avatar ?? null,
                ] : null,
                'body' => $message->body,
                'type' => $message->type,
                'file_url' => $message->file_url,
                'is_read' => false,
                'created_at' => $message->created_at?->toISOString(),
            ],
        ], 201);
    }

    /**
     * Mark messages as read
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

        $userId = $user->id;
        if ($chat->user1_id != $userId && $chat->user2_id != $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Mark all unread messages as read
        $unreadMessages = Message::where('chat_id', $chatId)
            ->where('sender_id', '!=', $userId)
            ->get();

        foreach ($unreadMessages as $message) {
            $message->markAsReadBy($userId);
        }

        // Reset unread count
        $chat->resetUnreadForUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read',
        ]);
    }
}

