<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_drift_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->unsignedSmallInteger('detected_at_week');
            $table->string('declared_profile', 20)->nullable();
            $table->string('observed_pattern', 40)->nullable();
            $table->string('drift_direction', 40)->nullable();         // e.g. "H→A"
            $table->string('drift_severity', 20)->default('minor');    // minor | moderate | major
            $table->timestamp('drift_confirmed_at')->nullable();
            $table->boolean('facilitator_alerted')->default(false);
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_drift_logs');
    }
};
