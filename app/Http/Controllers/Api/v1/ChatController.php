<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\PostEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Get or create a chat for a post between the authenticated user and post owner
     */
    public function getOrCreate(Request $request, int $postId): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Get post owner
        $post = \App\Models\Post::find($postId);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $postOwnerId = $post->user_id;
        $currentUserId = $user->id;

        // Can't chat with yourself
        if ($postOwnerId == $currentUserId) {
            return response()->json(['success' => false, 'message' => 'Cannot chat with yourself'], 400);
        }

        // Find existing chat or create new one
        // Ensure consistent ordering: user1_id < user2_id
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
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to create chat: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create chat: ' . $e->getMessage()
                ], 500);
            }
        }

        // Get other user info
        $otherUserId = $chat->getOtherUserId($currentUserId);
        $otherUser = \App\Models\User::find($otherUserId);

        // Get last message if exists
        $lastMessage = null;
        if ($chat->last_message_id) {
            $lastMessage = Message::find($chat->last_message_id);
        }

        // Get chat ID - MongoDB uses _id
        $chatId = $chat->_id ?? $chat->id ?? $chat->getKey();

        // Analytics: chat opened (best-effort)
        try {
            PostEvent::create([
                'post_id' => (int) $postId,
                'user_id' => (int) $currentUserId,
                'event' => 'post_chat_open',
                'meta' => [
                    'ip' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'chat_id' => (string) $chatId,
                    'target_user_id' => (int) $postOwnerId,
                ],
            ]);
        } catch (\Throwable $e) {
            // ignore analytics failures
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $chatId,
                'post_id' => $chat->post_id,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'email' => $otherUser->email,
                    'avatar' => $otherUser->avatar ?? null,
                ] : null,
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                ],
                'unread_count' => $chat->getUnreadCountForUser($currentUserId),
                'last_message' => $lastMessage ? [
                    'id' => (string) $lastMessage->_id,
                    'body' => $lastMessage->body,
                    'sent_at' => $lastMessage->created_at?->toISOString(),
                ] : null,
                'created_at' => $chat->created_at?->toISOString(),
            ],
        ]);
    }

    /**
     * List all chats for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $userId = $user->id;
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        // Get chats where user is either user1 or user2, and not deleted by them
        $chats = Chat::where(function ($query) use ($userId) {
            $query->where('user1_id', $userId)
                ->where('deleted_by_user1', false);
        })
        ->orWhere(function ($query) use ($userId) {
            $query->where('user2_id', $userId)
                ->where('deleted_by_user2', false);
        })
        ->orderBy('last_message_at', 'desc')
        ->skip(($page - 1) * $perPage)
        ->take($perPage)
        ->get();

        $chatsData = [];
        foreach ($chats as $chat) {
            $otherUserId = $chat->getOtherUserId($userId);
            $otherUser = \App\Models\User::find($otherUserId);
            $post = \App\Models\Post::find($chat->post_id);

            $lastMessage = null;
            if ($chat->last_message_id) {
                $lastMessage = Message::find($chat->last_message_id);
            }

            $chatId = $chat->_id ?? $chat->id ?? $chat->getKey();
            $lastMessageId = $lastMessage ? ($lastMessage->_id ?? $lastMessage->id ?? $lastMessage->getKey()) : null;

            $chatsData[] = [
                'id' => (string) $chatId,
                'post_id' => $chat->post_id,
                'post' => $post ? [
                    'id' => $post->id,
                    'title' => $post->title,
                ] : null,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'email' => $otherUser->email,
                    'avatar' => $otherUser->avatar ?? null,
                ] : null,
                'unread_count' => $chat->getUnreadCountForUser($userId),
                'last_message' => $lastMessage ? [
                    'id' => (string) $lastMessageId,
                    'body' => $lastMessage->body,
                    'sent_at' => $lastMessage->created_at?->toISOString(),
                    'sender_id' => $lastMessage->sender_id,
                ] : null,
                'last_message_at' => $chat->last_message_at?->toISOString(),
                'created_at' => $chat->created_at?->toISOString(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $chatsData,
        ]);
    }

    /**
     * Get a specific chat by ID
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

        $userId = $user->id;
        if ($chat->user1_id != $userId && $chat->user2_id != $userId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $otherUserId = $chat->getOtherUserId($userId);
        $otherUser = \App\Models\User::find($otherUserId);
        $post = \App\Models\Post::find($chat->post_id);

        $lastMessage = null;
        if ($chat->last_message_id) {
            $lastMessage = Message::find($chat->last_message_id);
        }

        $chatId = $chat->_id ?? $chat->id ?? $chat->getKey();
        $lastMessageId = $lastMessage ? ($lastMessage->_id ?? $lastMessage->id ?? $lastMessage->getKey()) : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => (string) $chatId,
                'post_id' => $chat->post_id,
                'post' => $post ? [
                    'id' => $post->id,
                    'title' => $post->title,
                ] : null,
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'email' => $otherUser->email,
                    'avatar' => $otherUser->avatar ?? null,
                ] : null,
                'unread_count' => $chat->getUnreadCountForUser($userId),
                'last_message' => $lastMessage ? [
                    'id' => (string) $lastMessageId,
                    'body' => $lastMessage->body,
                    'sent_at' => $lastMessage->created_at?->toISOString(),
                ] : null,
                'created_at' => $chat->created_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Mark chat as read (reset unread count)
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

        $chat->resetUnreadForUser($userId);

        return response()->json([
            'success' => true,
            'message' => 'Chat marked as read',
        ]);
    }

    /**
     * Delete/Archive a chat for the current user
     */
    public function delete(Request $request, string $chatId): JsonResponse
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

        if ($chat->user1_id == $userId) {
            $chat->update(['deleted_by_user1' => true]);
        } else {
            $chat->update(['deleted_by_user2' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chat deleted',
        ]);
    }
}

