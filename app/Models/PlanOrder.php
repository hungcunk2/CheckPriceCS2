<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'plan',
        'months',
        'amount_vnd',
        'reference',
        'status',
        'member_note',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
