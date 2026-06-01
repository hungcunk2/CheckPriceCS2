<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    protected $fillable = [
        'cny_to_vnd',
        'vnd_to_usd',
        'empire_coin_to_vnd',
        'empire_coin_to_usd',
    ];

    protected function casts(): array
    {
        return [
            'cny_to_vnd' => 'float',
            'vnd_to_usd' => 'float',
            'empire_coin_to_vnd' => 'float',
            'empire_coin_to_usd' => 'float',
        ];
    }
}
