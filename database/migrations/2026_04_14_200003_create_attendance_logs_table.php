<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id')->index();
            $table->uuid('student_id')->index();
            $table->string('status', 20)->default('present');           // present | absent | late | excused
            $table->text('remarks')->nullable();
            $table->uuid('taken_by')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'student_id']);
            $table->foreign('session_id')->references('id')->on('attendance_sessions')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
