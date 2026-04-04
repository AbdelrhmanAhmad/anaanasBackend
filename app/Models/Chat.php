<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Chat model stored in MongoDB.
 * Each chat is associated with a post and involves two users.
 * Multiple chats can exist between the same two users for different posts.
 */
class Chat extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'chats';

    protected $fillable = [
        'post_id',
        'user1_id',      // First user (initiator or alphabetically first)
        'user2_id',      // Second user
        'last_message_id', // Reference to last message for quick access
        'last_message_at',
        'unread_count_user1', // Unread messages count for user1
        'unread_count_user2', // Unread messages count for user2
        'archived_by_user1',
        'archived_by_user2',
        'deleted_by_user1',
        'deleted_by_user2',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'archived_by_user1' => 'boolean',
        'archived_by_user2' => 'boolean',
        'deleted_by_user1' => 'boolean',
        'deleted_by_user2' => 'boolean',
        'unread_count_user1' => 'integer',
        'unread_count_user2' => 'integer',
    ];

    /**
     * Get messages for this chat
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'chat_id');
    }

    /**
     * Get the other user in the chat
     */
    public function getOtherUserId(int $currentUserId): ?int
    {
        if ($this->user1_id == $currentUserId) {
            return $this->user2_id;
        }
        if ($this->user2_id == $currentUserId) {
            return $this->user1_id;
        }
        return null;
    }

    /**
     * Get unread count for a specific user
     */
    public function getUnreadCountForUser(int $userId): int
    {
        if ($this->user1_id == $userId) {
            return $this->unread_count_user1 ?? 0;
        }
        if ($this->user2_id == $userId) {
            return $this->unread_count_user2 ?? 0;
        }
        return 0;
    }

    /**
     * Increment unread count for a user
     */
    public function incrementUnreadForUser(int $userId): void
    {
        if ($this->user1_id == $userId) {
            $this->increment('unread_count_user1');
        } elseif ($this->user2_id == $userId) {
            $this->increment('unread_count_user2');
        }
    }

    /**
     * Reset unread count for a user
     */
    public function resetUnreadForUser(int $userId): void
    {
        if ($this->user1_id == $userId) {
            $this->update(['unread_count_user1' => 0]);
        } elseif ($this->user2_id == $userId) {
            $this->update(['unread_count_user2' => 0]);
        }
    }
}

