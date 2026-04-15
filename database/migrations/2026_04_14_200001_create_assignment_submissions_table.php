<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->index();
            $table->uuid('student_id')->index();
            $table->uuid('course_id')->index();
            $table->string('status', 30)->default('draft');             // draft | submitted | graded | returned
            $table->text('submission_text')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('grade', 8, 2)->nullable();
            $table->uuid('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->boolean('late')->default(false);
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
