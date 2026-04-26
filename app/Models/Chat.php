<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Chat model stored in MongoDB.
 *
 * A chat is created on-demand between the post owner and a buyer/visitor.
 * It is keyed by (post_id, user1_id, user2_id) where (user1, user2) is the
 * sorted ordered pair so we never end up with duplicates.
 *
 * Supports per-side moderation:
 *   - delete:  soft-hide chat from "my list"
 *   - clear:   per-user "delete history" timestamp; messages older than this
 *              are hidden from that user's view but kept for the counterparty
 *   - close:   conversation becomes read-only for both participants
 *   - block:   the blocker prevents the other participant from sending new
 *              messages (and from seeing typing/read receipts)
 */
class Chat extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'chats';

    protected $fillable = [
        'post_id',
        'user1_id',
        'user2_id',
        'last_message_id',
        'last_message_at',
        'unread_count_user1',
        'unread_count_user2',
        'archived_by_user1',
        'archived_by_user2',
        'deleted_by_user1',
        'deleted_by_user2',

        // Per-user "clear history" cutoff timestamps
        'cleared_at_user1',
        'cleared_at_user2',

        // Close (read-only) state
        'closed',
        'closed_by',
        'closed_at',

        // Block state — populated with the user_id that blocked the other side
        'blocked_by_user1',
        'blocked_by_user2',
        'blocked_at_user1',
        'blocked_at_user2',

        // Typing indicators (transient — broadcast in real time and stored
        // briefly so polling clients can also display them)
        'typing_user_id',
        'typing_at',

        // Aggregate counters used by the admin moderation UI
        'reports_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'archived_by_user1' => 'boolean',
        'archived_by_user2' => 'boolean',
        'deleted_by_user1' => 'boolean',
        'deleted_by_user2' => 'boolean',
        'unread_count_user1' => 'integer',
        'unread_count_user2' => 'integer',
        'cleared_at_user1' => 'datetime',
        'cleared_at_user2' => 'datetime',
        'closed' => 'boolean',
        'closed_at' => 'datetime',
        'blocked_by_user1' => 'boolean',
        'blocked_by_user2' => 'boolean',
        'blocked_at_user1' => 'datetime',
        'blocked_at_user2' => 'datetime',
        'typing_at' => 'datetime',
        'reports_count' => 'integer',
    ];

    public function messages()
    {
        return $this->hasMany(Message::class, 'chat_id');
    }

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

    public function incrementUnreadForUser(int $userId): void
    {
        if ($this->user1_id == $userId) {
            $this->increment('unread_count_user1');
        } elseif ($this->user2_id == $userId) {
            $this->increment('unread_count_user2');
        }
    }

    public function resetUnreadForUser(int $userId): void
    {
        if ($this->user1_id == $userId) {
            $this->update(['unread_count_user1' => 0]);
        } elseif ($this->user2_id == $userId) {
            $this->update(['unread_count_user2' => 0]);
        }
    }

    public function isParticipant(int $userId): bool
    {
        return $this->user1_id == $userId || $this->user2_id == $userId;
    }

    /**
     * Returns the per-user clear cutoff (UTC datetime) or null.
     */
    public function getClearedAtForUser(int $userId): ?\DateTimeInterface
    {
        if ($this->user1_id == $userId) return $this->cleared_at_user1;
        if ($this->user2_id == $userId) return $this->cleared_at_user2;
        return null;
    }

    public function clearForUser(int $userId): void
    {
        $now = now();
        if ($this->user1_id == $userId) {
            $this->update([
                'cleared_at_user1' => $now,
                'unread_count_user1' => 0,
            ]);
        } elseif ($this->user2_id == $userId) {
            $this->update([
                'cleared_at_user2' => $now,
                'unread_count_user2' => 0,
            ]);
        }
    }

    public function close(int $byUserId): void
    {
        $this->update([
            'closed' => true,
            'closed_by' => $byUserId,
            'closed_at' => now(),
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'closed' => false,
            'closed_by' => null,
            'closed_at' => null,
        ]);
    }

    /**
     * Mark `byUserId` as having blocked the other side. Returns true on
     * success; returns false if the user is not part of the chat.
     */
    public function blockByUser(int $byUserId): bool
    {
        $now = now();
        if ($this->user1_id == $byUserId) {
            $this->update([
                'blocked_by_user1' => true,
                'blocked_at_user1' => $now,
            ]);
            return true;
        }
        if ($this->user2_id == $byUserId) {
            $this->update([
                'blocked_by_user2' => true,
                'blocked_at_user2' => $now,
            ]);
            return true;
        }
        return false;
    }

    public function unblockByUser(int $byUserId): bool
    {
        if ($this->user1_id == $byUserId) {
            $this->update([
                'blocked_by_user1' => false,
                'blocked_at_user1' => null,
            ]);
            return true;
        }
        if ($this->user2_id == $byUserId) {
            $this->update([
                'blocked_by_user2' => false,
                'blocked_at_user2' => null,
            ]);
            return true;
        }
        return false;
    }

    public function isBlockedByUser(int $userId): bool
    {
        if ($this->user1_id == $userId) return (bool) $this->blocked_by_user1;
        if ($this->user2_id == $userId) return (bool) $this->blocked_by_user2;
        return false;
    }

    public function isBlockedForUser(int $userId): bool
    {
        // A user can no longer write to a chat if the *other* side blocked
        // them. (Or, of course, if they themselves issued the block — the UI
        // hides the composer in either case.)
        if ($this->user1_id == $userId) {
            return (bool) ($this->blocked_by_user2 || $this->blocked_by_user1);
        }
        if ($this->user2_id == $userId) {
            return (bool) ($this->blocked_by_user1 || $this->blocked_by_user2);
        }
        return false;
    }

    public function setTyping(int $userId): void
    {
        $this->update([
            'typing_user_id' => $userId,
            'typing_at' => now(),
        ]);
    }

    public function incrementReports(): void
    {
        $this->increment('reports_count');
    }
}
