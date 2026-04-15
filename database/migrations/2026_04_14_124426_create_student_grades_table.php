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
        Schema::create('student_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grade_item_id')->index();
            $table->uuid('student_id')->index();
            $table->string('student_name')->nullable();
            $table->decimal('grade', 8, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->date('submitted_date')->nullable();
            $table->string('status', 30)->default('pending');           // pending | graded | released
            $table->timestamps();

            $table->foreign('grade_item_id')->references('id')->on('grade_items')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_grades');
    }
};
