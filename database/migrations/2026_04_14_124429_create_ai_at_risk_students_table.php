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
        Schema::create('ai_at_risk_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->index();
            $table->uuid('student_id')->index();
            $table->string('student_name')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('last_access')->nullable();
            $table->unsignedInteger('missed_activities')->default(0);
            $table->decimal('grade', 5, 2)->nullable();
            $table->string('risk_level', 20)->default('medium');        // low | medium | high | critical
            $table->text('ai_recommendation')->nullable();
            $table->date('detected_at')->nullable();
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_at_risk_students');
    }
};
