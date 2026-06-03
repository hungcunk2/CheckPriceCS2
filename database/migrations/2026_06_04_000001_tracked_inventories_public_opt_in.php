<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Kho admin mặc định không hiện trên /bang-gia — bật thủ công trong form kho.
     */
    public function up(): void
    {
        DB::table('tracked_inventories')->update(['is_public' => false]);
    }

    public function down(): void
    {
        // Không khôi phục — admin bật lại từng kho nếu cần.
    }
};
