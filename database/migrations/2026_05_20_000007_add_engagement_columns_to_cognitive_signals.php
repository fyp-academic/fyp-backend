<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cognitive_signals', function (Blueprint $table) {
            $table->unsignedSmallInteger('assignment_revision_count')->default(0)->after('feedback_uptake_lag_hours');
            $table->decimal('quiz_question_skip_rate', 5, 2)->default(0)->after('assignment_revision_count');
            $table->decimal('avg_time_per_question_seconds', 7, 2)->nullable()->after('quiz_question_skip_rate');
            $table->decimal('material_completion_depth', 5, 2)->default(0)->after('avg_time_per_question_seconds');
            // avg scroll/watch % across all materials this week
        });
    }

    public function down(): void
    {
        Schema::table('cognitive_signals', function (Blueprint $table) {
            $table->dropColumn([
                'assignment_revision_count',
                'quiz_question_skip_rate',
                'avg_time_per_question_seconds',
                'material_completion_depth',
            ]);
        });
    }
};
