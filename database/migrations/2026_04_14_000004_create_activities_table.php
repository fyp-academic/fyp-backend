<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('section_id')->index();
            $table->uuid('course_id')->index();
            $table->string('type', 40)->index();                         // 20 Moodle tool types
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('visible')->default(true);
            $table->string('completion_status', 30)->default('none');    // none | incomplete | completed
            $table->decimal('grade_max', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();                        // tool-specific config (BBB, H5P, File, Page, etc.)
            $table->timestamps();

            $table->foreign('section_id')->references('id')->on('sections')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
