<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemCatalogImage extends Model
{
    protected $table = 'item_catalog_images';

    protected $fillable = [
        'market_hash_name',
        'image_url',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];
}

