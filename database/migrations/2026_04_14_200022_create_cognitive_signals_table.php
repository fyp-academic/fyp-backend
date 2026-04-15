<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cognitive_signals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->unsignedSmallInteger('week_number');
            $table->decimal('content_revisit_rate', 5, 2)->default(0);
            $table->boolean('revisit_flag')->default(false);
            $table->decimal('quiz_first_attempt_score', 5, 2)->nullable();
            $table->decimal('quiz_final_attempt_score', 5, 2)->nullable();
            $table->decimal('quiz_learning_delta', 6, 2)->nullable();
            $table->decimal('discussion_depth_score', 5, 2)->default(0);
            $table->decimal('avg_post_word_count', 7, 2)->default(0);
            $table->unsignedSmallInteger('question_count')->default(0);
            $table->unsignedSmallInteger('assertion_count')->default(0);
            $table->decimal('peer_response_rate', 5, 2)->default(0);
            $table->decimal('optional_resource_access_rate', 5, 2)->default(0);
            $table->decimal('feedback_uptake_lag_hours', 7, 2)->nullable();
            $table->json('normalised_values')->nullable();
            $table->json('colour_flags')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'course_id', 'week_number']);
            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cognitive_signals');
    }
};
