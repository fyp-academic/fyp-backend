<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->index();
            $table->string('type', 40)->default('text');                // text | rating | multiple_choice | yes_no
            $table->text('question_text');
            $table->json('options')->nullable();                        // for multiple_choice
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_questions');
    }
};
