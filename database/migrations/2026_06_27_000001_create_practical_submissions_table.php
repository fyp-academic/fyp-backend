<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practical_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->index();
            $table->uuid('student_id')->index();
            $table->uuid('course_id')->index();
            $table->json('files');                       // { html, css, js }
            $table->string('status')->default('draft');  // draft | submitted
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('grade', 8, 2)->nullable();
            $table->uuid('graded_by')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'student_id']);
            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practical_submissions');
    }
};
