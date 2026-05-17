<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_inventories', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable();
            $table->text('url');
            $table->string('steam_id', 20)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->decimal('last_total_cny', 14, 2)->nullable();
            $table->unsignedBigInteger('last_total_vnd')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_inventories');
    }
};
