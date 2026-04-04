<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Storage;

class PostImages extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'post_id',
        'image',
    ];

    protected $appends = [
        'image_full_url',
    ] ;
    public function getImageFullUrlAttribute()
    {

        return Storage::disk('s3')->url($this->image);
//          return  asset("storage/" . $this->image) ;
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
