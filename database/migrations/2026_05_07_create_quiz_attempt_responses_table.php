<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempt_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->uuid('answer_id')->nullable(); // For selected answer
            $table->text('response_text')->nullable(); // For text/essay answers
            $table->decimal('marks_awarded', 8, 2)->nullable();
            $table->decimal('marks_max', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->foreign('attempt_id')->references('id')->on('quiz_attempts')->onDelete('cascade');
            $table->foreign('question_id')->references('id')->on('quiz_questions')->onDelete('cascade');
            $table->foreign('answer_id')->references('id')->on('quiz_answers')->onDelete('set null');

            $table->index(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_responses');
    }
};
