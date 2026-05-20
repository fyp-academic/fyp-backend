<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('material_id')->index();
            $table->uuid('student_id')->index();
            $table->uuid('course_id')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->unsignedInteger('total_duration_seconds')->default(0);
            $table->unsignedSmallInteger('open_count')->default(0);
            $table->decimal('completion_percent', 5, 2)->default(0);       // 0.00-100.00
            $table->decimal('video_watch_percent', 5, 2)->nullable();       // null for non-video
            $table->unsignedSmallInteger('rewatch_count')->default(0);      // video-specific
            $table->decimal('pdf_scroll_depth_percent', 5, 2)->nullable();  // null for non-pdf
            $table->boolean('downloaded')->default(false);
            $table->timestamps();

            $table->unique(['material_id', 'student_id']);
            $table->index(['student_id', 'course_id']);
            $table->foreign('material_id')->references('id')->on('course_materials')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_interactions');
    }
};
