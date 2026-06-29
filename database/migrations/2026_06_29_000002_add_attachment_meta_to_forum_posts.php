<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forum posts already have `attachment_path`; add the metadata columns (mirroring
 * the messages table) so a voice-note / file attachment can be rendered with the
 * right player and label.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_type', 60)->nullable()->after('attachment_name');
            $table->bigInteger('attachment_size')->nullable()->after('attachment_type');
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn(['attachment_name', 'attachment_type', 'attachment_size']);
        });
    }
};
