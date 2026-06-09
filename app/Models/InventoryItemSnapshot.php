<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItemSnapshot extends Model
{
    protected $fillable = [
        'inventory_id',
        'asset_id',
        'market_hash_name',
        'display_name',
        'amount',
        'buff_price_cny',
        'line_total_cny',
        'empire_price_cny',
        'line_total_empire_cny',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'buff_price_cny' => 'float',
            'line_total_cny' => 'float',
            'empire_price_cny' => 'float',
            'line_total_empire_cny' => 'float',
            'recorded_at' => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(TrackedInventory::class, 'inventory_id');
    }
}
