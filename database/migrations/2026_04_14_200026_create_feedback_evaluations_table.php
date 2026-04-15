<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('intervention_id')->index();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->unsignedSmallInteger('evaluated_at_week');
            $table->decimal('score_before', 5, 2)->nullable();
            $table->decimal('score_at_t7', 5, 2)->nullable();
            $table->decimal('score_at_t14', 5, 2)->nullable();
            $table->decimal('score_delta_t7', 6, 2)->nullable();
            $table->decimal('score_delta_t14', 6, 2)->nullable();
            $table->boolean('recovery_threshold_met')->default(false);
            $table->string('outcome_label', 40)->nullable();           // recovered | partial | no_change | worsened
            $table->decimal('re_threshold_adjustment', 5, 2)->nullable();
            $table->text('model_notes')->nullable();
            $table->timestamps();

            $table->foreign('intervention_id')->references('id')->on('interventions')->cascadeOnDelete();
            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_evaluations');
    }
};
