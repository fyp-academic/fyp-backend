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
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('question_id')->index();
            $table->text('text');
            $table->decimal('grade_fraction', 4, 3)->default(0);       // 1.000 = correct, 0.000 = wrong, partial allowed
            $table->text('feedback')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreign('question_id')->references('id')->on('quiz_questions')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
    }
};
