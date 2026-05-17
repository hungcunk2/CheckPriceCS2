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
            $table->string('steam_persona_name')->nullable();
            $table->string('steam_avatar_url', 512)->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->decimal('last_total_cny', 14, 2)->nullable();
            $table->unsignedBigInteger('last_total_vnd')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->unsignedInteger('priced_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('last_snapshot')->nullable();
            $table->timestamps();

            $table->index('is_public');
            $table->index('updated_at');
        });

        Schema::create('price_history_points', function (Blueprint $table) {
            $table->id();
            $table->char('item_hash', 32);
            $table->string('market_hash_name', 512);
            $table->timestamp('recorded_at');
            $table->decimal('price_cny', 12, 2);
            $table->unsignedInteger('sell_num')->nullable();
            $table->timestamps();

            $table->index(['item_hash', 'recorded_at']);
            $table->unique(['item_hash', 'recorded_at', 'price_cny', 'sell_num'], 'price_history_points_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_history_points');
        Schema::dropIfExists('tracked_inventories');
    }
};
