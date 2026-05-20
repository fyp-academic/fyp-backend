<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->unsignedSmallInteger('week_number');

            // Component scores (0-100 each)
            $table->decimal('login_consistency_score', 5, 2)->default(0);   // L
            $table->decimal('content_completion_score', 5, 2)->default(0);  // C
            $table->decimal('assessment_activity_score', 5, 2)->default(0); // A
            $table->decimal('forum_participation_score', 5, 2)->default(0); // F
            $table->decimal('pacing_score', 5, 2)->default(0);              // P
            $table->decimal('live_session_score', 5, 2)->default(0);        // S

            // Final weighted score: E = 0.15L + 0.25C + 0.20A + 0.15F + 0.15P + 0.10S
            $table->decimal('engagement_score', 5, 2)->default(0);
            $table->decimal('previous_week_score', 5, 2)->nullable();
            $table->decimal('score_delta', 6, 2)->nullable();

            $table->json('component_breakdown')->nullable();  // JSON details for each component
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'course_id', 'week_number']);
            $table->index(['course_id', 'week_number']);
            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagement_scores');
    }
};
