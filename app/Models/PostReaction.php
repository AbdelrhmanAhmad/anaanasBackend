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

    protected $fillable = [
        'post_id',
        'user_id',
        'type', // like | future types
    ];
}


