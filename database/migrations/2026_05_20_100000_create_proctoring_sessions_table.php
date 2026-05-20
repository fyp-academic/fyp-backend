<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctoring_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('activity_id');
            $table->uuid('course_id')->nullable();
            $table->string('context_type')->default('quiz');         // quiz | assignment
            $table->uuid('quiz_attempt_id')->nullable();
            $table->uuid('assignment_submission_id')->nullable();
            $table->string('status')->default('active');             // active | ended | force_submitted
            $table->integer('violation_count')->default(0);
            $table->boolean('is_flagged')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctoring_sessions');
    }
};
