<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class MemberInventorySchema
{
    public static function isReady(): bool
    {
        return Schema::hasTable('tracked_inventories')
            && Schema::hasColumn('tracked_inventories', 'user_id');
    }
}
