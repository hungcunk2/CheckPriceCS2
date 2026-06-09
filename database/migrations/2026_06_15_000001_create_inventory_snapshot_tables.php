<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_value_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('tracked_inventories')->cascadeOnDelete();
            $table->decimal('total_cny', 14, 2)->default(0);
            $table->unsignedBigInteger('total_vnd')->default(0);
            $table->decimal('total_empire_cny', 14, 2)->nullable();
            $table->unsignedBigInteger('total_empire_vnd')->nullable();
            $table->unsignedInteger('item_count')->default(0);
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['inventory_id', 'recorded_at']);
            $table->index('recorded_at');
        });

        Schema::create('inventory_item_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_id')->constrained('tracked_inventories')->cascadeOnDelete();
            $table->string('asset_id', 64);
            $table->string('market_hash_name');
            $table->string('display_name')->nullable();
            $table->unsignedSmallInteger('amount')->default(1);
            $table->decimal('buff_price_cny', 12, 2)->nullable();
            $table->decimal('line_total_cny', 14, 2)->nullable();
            $table->decimal('empire_price_cny', 12, 2)->nullable();
            $table->decimal('line_total_empire_cny', 14, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['inventory_id', 'recorded_at']);
            $table->index(['inventory_id', 'asset_id', 'recorded_at'], 'inv_item_snap_inv_asset_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_item_snapshots');
        Schema::dropIfExists('inventory_value_snapshots');
    }
};
