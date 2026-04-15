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
        Schema::create('ai_content_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->index();
            $table->string('title');
            $table->string('content_type', 40)->nullable();             // video | article | quiz | exercise
            $table->decimal('relevance_score', 4, 2)->default(0);
            $table->string('source', 100)->nullable();
            $table->string('url')->nullable();
            $table->date('generated_at')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_content_recommendations');
    }
};
