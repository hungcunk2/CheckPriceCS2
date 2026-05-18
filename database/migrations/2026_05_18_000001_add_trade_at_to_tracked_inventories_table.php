<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracked_inventories', function (Blueprint $table) {
            $table->timestamp('trade_at')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('tracked_inventories', function (Blueprint $table) {
            $table->dropColumn('trade_at');
        });
    }
};
