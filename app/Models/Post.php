<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;
    protected $connection  = "mysql";
    // public $incrementing = false;
    // public $timestamps = false;

    protected $fillable = [
     'id',
     'user_id',
     'section_id',
     'category_id',
     'country_id',
     'city_id',
     'title',
     'description',
     'price',
     'status',
     'post_type',
     'main_image',
     'location',
     'publish_date',
    ] ;

    protected $casts = [
        'publish_date' => 'datetime',
        'location' => 'array',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
    public function postImages(): HasMany
    {
        return $this->hasMany(PostImages::class, 'post_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
    public function postData()
    {
        return $this->hasOne(PostData::class, 'post_id')
//            ->setConnection('mongodb')
            ;

    }





    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function auctionLot(): HasOne
    {
        return $this->hasOne(AuctionLot::class, 'post_id');
    }
}
