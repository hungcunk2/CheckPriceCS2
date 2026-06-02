<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cs2CapApiKey extends Model
{
    protected $table = 'cs2cap_api_keys';

    protected $fillable = [
        'label',
        'api_key',
        'is_active',
        'sort_order',
    ];
}

