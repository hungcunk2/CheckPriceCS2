<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_inventories', function (Blueprint $table) {
            if (! Schema::hasColumn('tracked_inventories', 'admin_username')) {
                $table->string('admin_username', 64)->nullable()->after('user_id');
                $table->index('admin_username');
            }

            if (! Schema::hasColumn('tracked_inventories', 'notes')) {
                $table->text('notes')->nullable()->after('label');
            }
        });

        if (Schema::hasColumn('tracked_inventories', 'admin_username')) {
            $defaultAdmin = (string) config('admin.username', 'admin');
            DB::table('tracked_inventories')
                ->whereNull('user_id')
                ->whereNull('admin_username')
                ->update(['admin_username' => $defaultAdmin]);
        }
    }

    public function down(): void
    {
        Schema::table('tracked_inventories', function (Blueprint $table) {
            if (Schema::hasColumn('tracked_inventories', 'admin_username')) {
                $table->dropIndex(['admin_username']);
                $table->dropColumn('admin_username');
            }

            if (Schema::hasColumn('tracked_inventories', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
