<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_chunks', function (Blueprint $table) {
            $table->string('content_source', 30)->default('lesson_page')->after('content_id');
            $table->index(['content_source', 'content_id']);
        });

        Schema::table('content_chunks', function (Blueprint $table) {
            $table->dropForeign(['content_id']);
        });
    }

    public function down(): void
    {
        Schema::table('content_chunks', function (Blueprint $table) {
            $table->foreign('content_id')->references('id')->on('lesson_pages')->cascadeOnDelete();
            $table->dropIndex(['content_source', 'content_id']);
            $table->dropColumn('content_source');
        });
    }
};
