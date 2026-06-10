<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_chunks', function (Blueprint $table) {
            $table->string('semantic_role', 20)->nullable()->after('chunk_type');
            $table->json('key_terms')->nullable()->after('semantic_role');
            $table->unsignedTinyInteger('lesson_position_pct')->nullable()->after('key_terms');
            $table->index(['content_id', 'semantic_role'], 'chunks_content_role_idx');
        });
    }

    public function down(): void
    {
        Schema::table('content_chunks', function (Blueprint $table) {
            $table->dropIndex('chunks_content_role_idx');
            $table->dropColumn(['semantic_role', 'key_terms', 'lesson_position_pct']);
        });
    }
};
