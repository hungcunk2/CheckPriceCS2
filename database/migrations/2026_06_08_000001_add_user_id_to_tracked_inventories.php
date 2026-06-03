<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tracked_inventories', 'user_id')) {
            return;
        }

        Schema::table('tracked_inventories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tracked_inventories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
