<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tracked_inventories')) {
            return;
        }

        $defaultAdmin = (string) config('admin.username', 'admin');

        if (Schema::hasColumn('tracked_inventories', 'user_id')
            && Schema::hasColumn('tracked_inventories', 'steam_id')) {
            $memberSteamIds = DB::table('tracked_inventories')
                ->whereNotNull('user_id')
                ->whereNotNull('steam_id')
                ->where('steam_id', '!=', '')
                ->pluck('steam_id')
                ->unique()
                ->values()
                ->all();

            if ($memberSteamIds !== []) {
                DB::table('tracked_inventories')
                    ->whereNull('user_id')
                    ->whereIn('steam_id', $memberSteamIds)
                    ->delete();
            }
        }

        if (Schema::hasColumn('tracked_inventories', 'admin_username')) {
            DB::table('tracked_inventories')
                ->whereNull('user_id')
                ->whereNull('admin_username')
                ->update(['admin_username' => $defaultAdmin]);
        }
    }

    public function down(): void
    {
        //
    }
};
