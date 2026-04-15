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
        Schema::create('activity_performance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->index();
            $table->string('activity_name');
            $table->decimal('avg_score_percentage', 5, 2)->default(0);
            $table->decimal('grade_max', 8, 2)->default(100);
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
        Schema::dropIfExists('activity_performance');
    }
};
