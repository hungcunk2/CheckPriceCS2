<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackedInventory extends Model
{
    protected $fillable = [
        'user_id',
        'label',
        'url',
        'steam_id',
        'steam_persona_name',
        'steam_avatar_url',
        'is_public',
        'sort_order',
        'trade_at',
        'last_checked_at',
        'last_total_cny',
        'last_total_vnd',
        'item_count',
        'priced_count',
        'failed_count',
        'last_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'trade_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'last_total_cny' => 'float',
            'last_snapshot' => 'array',
        ];
    }
}
