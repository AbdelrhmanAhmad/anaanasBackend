<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuctionLot extends Model
{
    protected $fillable = [
        'post_id',
        'start_price',
        'current_price',
        'min_increment',
        'reserve_price',
        'start_at',
        'end_at',
        'status',
        'winner_user_id',
        'bids_count',
        'last_bid_at',
    ];

    protected $casts = [
        'start_price' => 'float',
        'current_price' => 'float',
        'min_increment' => 'float',
        'reserve_price' => 'float',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'last_bid_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(AuctionBid::class, 'auction_lot_id');
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(AuctionWatcher::class, 'auction_lot_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id');
    }
}

