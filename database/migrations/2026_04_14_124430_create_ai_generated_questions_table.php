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
        Schema::create('ai_generated_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->index();
            $table->uuid('activity_id')->nullable()->index();
            $table->string('topic', 150)->nullable();
            $table->text('question_text');
            $table->string('question_type', 40)->default('multiple_choice');
            $table->string('difficulty', 20)->default('medium');        // easy | medium | hard
            $table->string('status', 30)->default('pending');           // pending | approved | rejected
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('activity_id')->references('id')->on('activities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_generated_questions');
    }
};
