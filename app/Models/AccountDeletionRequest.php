<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDeletionRequest extends Model
{
    protected $fillable = [
        'user_id',
        'requested_at',
        'scheduled_deletion_at',
        'cancelled_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'scheduled_deletion_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /**
     * Get the user that owns the deletion request
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the deletion request is active (not cancelled)
     */
    public function isActive(): bool
    {
        return $this->cancelled_at === null && $this->deleted_at === null;
    }

    /**
     * Check if the deletion can be cancelled (within grace period)
     */
    public function canBeCancelled(): bool
    {
        return $this->isActive() && $this->scheduled_deletion_at->isFuture();
    }
}

