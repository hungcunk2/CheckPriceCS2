<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tracked_inventories') || ! Schema::hasColumn('tracked_inventories', 'is_public')) {
            return;
        }

        Schema::table('tracked_inventories', function (Blueprint $table) {
            $table->dropIndex(['is_public']);
            $table->dropColumn('is_public');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tracked_inventories') || Schema::hasColumn('tracked_inventories', 'is_public')) {
            return;
        }

        Schema::table('tracked_inventories', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('steam_avatar_url');
            $table->index('is_public');
        });
    }
};
