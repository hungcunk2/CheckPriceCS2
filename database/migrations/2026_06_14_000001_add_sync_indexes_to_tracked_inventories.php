<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_inventories', function (Blueprint $table) {
            $table->index('last_checked_at');
            $table->index('steam_id');
        });
    }

    public function down(): void
    {
        Schema::table('tracked_inventories', function (Blueprint $table) {
            $table->dropIndex(['last_checked_at']);
            $table->dropIndex(['steam_id']);
        });
    }
};
