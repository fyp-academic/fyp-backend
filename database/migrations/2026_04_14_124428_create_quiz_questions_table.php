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
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->index();
            $table->uuid('course_id')->index();
            $table->string('type', 40)->default('multiple_choice');    // multiple_choice | true_false | short_answer | matching | essay
            $table->text('question_text');
            $table->string('category', 100)->nullable();
            $table->decimal('default_mark', 6, 2)->default(1);
            $table->boolean('shuffle_answers')->default(false);
            $table->boolean('multiple_answers')->default(false);
            $table->text('correct_answer')->nullable();                 // for short_answer / essay
            $table->decimal('penalty', 4, 2)->default(0);
            $table->json('hints')->nullable();
            $table->json('matching_pairs')->nullable();                 // [{question,answer}] for matching type
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_questions');
    }
};
