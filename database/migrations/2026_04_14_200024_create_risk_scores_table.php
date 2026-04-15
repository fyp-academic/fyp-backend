<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->unsignedSmallInteger('week_number');
            $table->string('profile_type', 20)->nullable();            // H | A | T | C | mixed
            $table->decimal('l1_contribution', 5, 2)->default(0);     // behavioral layer weight
            $table->decimal('l2_contribution', 5, 2)->default(0);     // cognitive layer weight
            $table->decimal('l3_contribution', 5, 2)->default(0);     // emotional layer weight
            $table->decimal('primary_score', 5, 2)->default(0);
            $table->decimal('secondary_score', 5, 2)->default(0);
            $table->decimal('final_score', 5, 2)->default(0);
            $table->decimal('previous_week_score', 5, 2)->nullable();
            $table->decimal('score_delta', 6, 2)->nullable();
            $table->string('tier', 20)->default('green');              // green | amber | red | critical
            $table->boolean('anomaly_flag')->default(false);
            $table->json('signal_breakdown')->nullable();
            $table->text('facilitator_notes_prompt')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'course_id', 'week_number']);
            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_scores');
    }
};
