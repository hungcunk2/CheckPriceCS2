<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuffAccount extends Model
{
    protected $fillable = [
        'label',
        'session',
        'csrf_token',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'session' => 'encrypted',
            'csrf_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
