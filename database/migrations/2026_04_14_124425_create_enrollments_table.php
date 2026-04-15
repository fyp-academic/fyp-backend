<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('course_id')->index();
            $table->string('role', 30)->default('student');              // student | instructor | teaching_assistant | observer
            $table->date('enrolled_date')->nullable();
            $table->string('last_access')->nullable();                   // human-readable
            $table->unsignedTinyInteger('progress')->default(0);        // 0–100 percentage
            $table->json('groups')->nullable();                          // group names array
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
