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
        Schema::create('ai_performance_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->index();
            $table->string('week_label', 30);
            $table->decimal('avg_grade', 5, 2)->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->decimal('engagement_score', 5, 2)->default(0);
            $table->date('recorded_at');
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_performance_snapshots');
    }
};
