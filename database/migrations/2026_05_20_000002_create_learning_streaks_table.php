<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_streaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('learner_id')->index();
            $table->uuid('course_id')->index();
            $table->unsignedSmallInteger('current_streak_days')->default(0);
            $table->unsignedSmallInteger('longest_streak_days')->default(0);
            $table->date('last_active_date')->nullable();
            $table->timestamp('streak_broken_at')->nullable();
            $table->timestamps();

            $table->unique(['learner_id', 'course_id']);
            $table->foreign('learner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_streaks');
    }
};
