<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Append-only event log for posts (analytics).
 * Example: post_like / post_unlike
 */
class PostEvent extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'post_events';

    protected $fillable = [
        'post_id',
        'user_id',
        'event',     // post_like | post_unlike | ...
        'meta',      // optional array
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}


