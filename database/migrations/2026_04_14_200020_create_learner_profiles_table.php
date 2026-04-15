<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->string('primary_profile', 20)->nullable();         // H | A | T | C
            $table->string('secondary_profile', 20)->nullable();
            $table->boolean('is_mixed_profile')->default(false);
            $table->decimal('mixed_blend_primary', 5, 2)->nullable();
            $table->decimal('mixed_blend_secondary', 5, 2)->nullable();
            $table->decimal('h_score', 5, 2)->default(0);
            $table->decimal('a_score', 5, 2)->default(0);
            $table->decimal('t_score', 5, 2)->default(0);
            $table->decimal('c_score', 5, 2)->default(0);
            $table->json('declared_preferences')->nullable();
            $table->json('lms_flags')->nullable();
            $table->boolean('pulse_consent')->default(false);
            $table->timestamp('pulse_consent_at')->nullable();
            $table->boolean('drift_flag')->default(false);
            $table->unsignedSmallInteger('drift_weeks_count')->default(0);
            $table->timestamp('drift_flagged_at')->nullable();
            $table->text('profile_note')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'course_id']);
            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_profiles');
    }
};
