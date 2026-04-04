<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionBid extends Model
{
    protected $fillable = [
        'auction_lot_id',
        'user_id',
        'amount',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(AuctionLot::class, 'auction_lot_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

