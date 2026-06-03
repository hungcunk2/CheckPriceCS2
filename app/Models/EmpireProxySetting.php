<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpireProxySetting extends Model
{
    protected $fillable = [
        'enabled',
        'rotation_key',
        'nhamang',
        'tinhthanh',
        'whitelist_ip',
        'use_socks5',
        'last_test_message',
        'last_tested_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'use_socks5' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        $row = self::query()->first();
        if ($row) {
            return $row;
        }

        return self::query()->create([
            'enabled' => false,
            'nhamang' => 'Random',
            'tinhthanh' => '0',
        ]);
    }
}
