<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavioral_signals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->unsignedSmallInteger('week_number');
            $table->unsignedSmallInteger('login_frequency')->default(0);
            $table->decimal('time_on_task_hours', 6, 2)->default(0);
            $table->decimal('content_completion_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('quiz_attempt_count')->default(0);
            $table->unsignedSmallInteger('quiz_available_count')->default(0);
            $table->string('submission_timing', 30)->nullable();        // early | on_time | late | missing
            $table->unsignedSmallInteger('forum_post_count')->default(0);
            $table->unsignedSmallInteger('forum_posts_required')->default(0);
            $table->string('navigation_pattern', 40)->nullable();       // linear | random | revisit_heavy
            $table->json('normalised_values')->nullable();
            $table->json('colour_flags')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'course_id', 'week_number']);
            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavioral_signals');
    }
};
