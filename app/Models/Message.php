<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Message model stored in MongoDB.
 * Each message belongs to a chat.
 */
class Message extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'messages';

    protected $fillable = [
        'chat_id',
        'sender_id',
        'body',
        'type', // text | image | file
        'file_url', // For images/files
        'read_at',
        'read_by', // Array of user IDs who read this message
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'read_by' => 'array',
    ];

    /**
     * Get the chat this message belongs to
     */
    public function chat()
    {
        return $this->belongsTo(Chat::class, 'chat_id');
    }

    /**
     * Mark message as read by a user
     */
    public function markAsReadBy(int $userId): void
    {
        $readBy = $this->read_by ?? [];
        if (!in_array($userId, $readBy)) {
            $readBy[] = $userId;
            $this->update([
                'read_by' => $readBy,
                'read_at' => $this->read_at ?? now(),
            ]);
        }
    }

    /**
     * Check if message is read by a user
     */
    public function isReadBy(int $userId): bool
    {
        $readBy = $this->read_by ?? [];
        return in_array($userId, $readBy);
    }
}

