<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemPriceCache extends Model
{
    protected $table = 'item_price_caches';

    protected $fillable = [
        'source',
        'market_hash_name',
        'phase',
        'currency',
        'price',
        'payload',
        'fetched_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'price' => 'float',
        'fetched_at' => 'datetime',
    ];
}

