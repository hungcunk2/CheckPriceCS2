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

        if (Schema::hasColumn('tracked_inventories', 'admin_username')) {
            DB::table('tracked_inventories')
                ->whereNotNull('user_id')
                ->update(['admin_username' => null]);
        }

        if (Schema::hasColumn('tracked_inventories', 'user_id')) {
            $memberUrls = DB::table('tracked_inventories')
                ->whereNotNull('user_id')
                ->pluck('url')
                ->unique()
                ->filter()
                ->values()
                ->all();

            if ($memberUrls !== []) {
                DB::table('tracked_inventories')
                    ->whereNull('user_id')
                    ->whereIn('url', $memberUrls)
                    ->delete();
            }
        }
    }

    public function down(): void
    {
        // Không khôi phục bản ghi đã xóa.
    }
};
