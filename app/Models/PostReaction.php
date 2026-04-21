<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Current reaction state for a post (fast lookup).
 * Stored in MongoDB to avoid inflating MySQL.
 */
class PostReaction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'post_reactions';

    public const TYPE_LIKE = 'like';
    public const TYPE_LOVE = 'love';
    public const TYPE_CARE = 'care';
    public const TYPE_HAHA = 'haha';
    public const TYPE_WOW = 'wow';
    public const TYPE_SAD = 'sad';
    public const TYPE_ANGRY = 'angry';

    /**
     * Supported Facebook-like reaction types.
     *
     * @return array<int, string>
     */
    public static function allowedTypes(): array
    {
        return [
            self::TYPE_LIKE,
            self::TYPE_LOVE,
            self::TYPE_CARE,
            self::TYPE_HAHA,
            self::TYPE_WOW,
            self::TYPE_SAD,
            self::TYPE_ANGRY,
        ];
    }

    protected $fillable = [
        'post_id',
        'user_id',
        'type', // like | future types
    ];
}


