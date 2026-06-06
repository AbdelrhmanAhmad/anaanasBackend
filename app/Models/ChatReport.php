<?php

namespace App\Models;

use MongoDB\BSON\ObjectId;
use MongoDB\Laravel\Eloquent\Model;

/**
 * ChatReport (MongoDB) — flagged conversations awaiting admin review.
 *
 * Each row captures who reported the chat, the reason, and provides the
 * admin with a stable link back to the original conversation so they can
 * load and inspect the messages without scattering them across MySQL.
 */
class ChatReport extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'chat_reports';

    public const STATUS_PENDING = 'pending';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_ACTION_TAKEN = 'action_taken';

    protected $fillable = [
        'chat_id',
        'post_id',
        'reporter_id',
        'reported_user_id',
        'reason',
        'category',     // spam | harassment | scam | inappropriate | other
        'description',
        'status',       // pending | reviewed | dismissed | action_taken
        'admin_id',
        'admin_notes',
        'reviewed_at',
        'snapshot',     // optional copy of the most recent message for fast triage
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'snapshot' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return '_id';
    }

    public function getRouteKey(): string
    {
        return (string) ($this->getAttribute('_id') ?? $this->getKey());
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_REVIEWED,
            self::STATUS_DISMISSED,
            self::STATUS_ACTION_TAKEN,
        ];
    }

    public static function categories(): array
    {
        return ['spam', 'harassment', 'scam', 'inappropriate', 'other'];
    }

    /**
     * Resolve a report document from the URL segment (hex string or ObjectId).
     */
    public static function findByRouteKey(string $value): ?self
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $field = (new static)->getRouteKeyName();

        $byString = static::query()->where($field, $value)->first();
        if ($byString) {
            return $byString;
        }

        if (strlen($value) === 24 && ctype_xdigit($value)) {
            try {
                $oid = new ObjectId($value);
                $byOid = static::query()->where($field, $oid)->first();
                if ($byOid) {
                    return $byOid;
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return static::query()->find($value);
    }

    /**
     * Filament / implicit route binding: URL segment is a 24-char hex string,
     * while MongoDB stores `_id` as ObjectId. Try both string and ObjectId.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        if (strlen($value) === 24 && ctype_xdigit($value)) {
            try {
                $oid = new ObjectId($value);

                return $query->where(function ($q) use ($field, $value, $oid) {
                    $q->where($field, $value)
                        ->orWhere($field, $oid)
                        ->orWhere('id', $value)
                        ->orWhere('id', $oid);
                });
            } catch (\Throwable) {
                // fall through
            }
        }

        return $query->where(function ($q) use ($field, $value) {
            $q->where($field, $value)->orWhere('id', $value);
        });
    }
}
