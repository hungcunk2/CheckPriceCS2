<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpireApiKey extends Model
{
    protected $fillable = [
        'label',
        'api_key',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }
}
