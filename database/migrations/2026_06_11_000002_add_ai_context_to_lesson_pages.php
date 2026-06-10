<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_pages', function (Blueprint $table) {
            $table->text('ai_context_summary')->nullable()->after('content');
            $table->timestamp('context_summary_at')->nullable()->after('ai_context_summary');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_pages', function (Blueprint $table) {
            $table->dropColumn(['ai_context_summary', 'context_summary_at']);
        });
    }
};
