<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * MongoDB collection for comment reactions (for performance).
 *
 * Document shape (example):
 * {
 *   comment_id: 123,
 *   post_id: 55,
 *   user_id: 9,
 *   type: "like",
 *   created_at: ISODate(...),
 *   updated_at: ISODate(...)
 * }
 */
class CommentReaction extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'comment_reactions';

    protected $fillable = [
        'comment_id',
        'post_id',
        'user_id',
        'type',
    ];
}


