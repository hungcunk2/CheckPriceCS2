<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->string('attachment_path', 512)->nullable()->after('body');
            $table->string('attachment_mime', 64)->nullable()->after('attachment_path');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_mime']);
            $table->text('body')->nullable(false)->change();
        });
    }
};
