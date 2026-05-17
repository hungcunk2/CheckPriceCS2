<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceHistoryPoint extends Model
{
    protected $fillable = [
        'item_hash',
        'market_hash_name',
        'recorded_at',
        'price_cny',
        'sell_num',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'price_cny' => 'float',
            'sell_num' => 'integer',
        ];
    }
}
