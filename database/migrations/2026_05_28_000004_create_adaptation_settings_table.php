<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adaptation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            // Maps to sections since those are the course topics/modules in this LMS
            $table->uuid('topic_id')->nullable();
            $table->boolean('allow_simplification')->default(true);
            $table->boolean('allow_example_substitution')->default(true);
            $table->boolean('allow_analogies')->default(true);
            $table->boolean('lock_technical_definitions')->default(true);
            $table->boolean('prevent_assessment_rewrite')->default(true);
            $table->integer('min_difficulty')->default(1);
            $table->integer('max_difficulty')->default(5);
            $table->float('ai_confidence_threshold')->default(0.75);
            $table->uuid('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['course_id', 'topic_id']);
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('topic_id')->references('id')->on('sections')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adaptation_settings');
    }
};
