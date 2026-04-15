<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emotional_signals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->unsignedSmallInteger('week_number');
            $table->decimal('pulse_confidence', 3, 2)->nullable();     // 0.00 – 1.00
            $table->decimal('pulse_energy', 3, 2)->nullable();
            $table->decimal('pulse_composite', 3, 2)->nullable();
            $table->boolean('pulse_submitted')->default(false);
            $table->timestamp('pulse_submitted_at')->nullable();
            $table->decimal('mood_drift_score', 5, 2)->nullable();
            $table->boolean('mood_drift_flag')->default(false);
            $table->decimal('help_seeking_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('messages_to_facilitator')->default(0);
            $table->unsignedSmallInteger('forum_questions_asked')->default(0);
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
        Schema::dropIfExists('emotional_signals');
    }
};
