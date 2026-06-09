<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryValueSnapshot extends Model
{
    protected $fillable = [
        'inventory_id',
        'total_cny',
        'total_vnd',
        'total_empire_cny',
        'total_empire_vnd',
        'item_count',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cny' => 'float',
            'total_vnd' => 'integer',
            'total_empire_cny' => 'float',
            'total_empire_vnd' => 'integer',
            'item_count' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(TrackedInventory::class, 'inventory_id');
    }
}
