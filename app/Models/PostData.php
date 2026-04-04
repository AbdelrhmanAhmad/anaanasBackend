<?php

namespace App\Models;


use MongoDB\Laravel\Eloquent\Model;

class PostData extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'posts';
    protected $guarded = [] ;
    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
