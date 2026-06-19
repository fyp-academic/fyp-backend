<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('badge_id')->index();
            $table->uuid('course_id')->nullable()->index();   // null = global achievement
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->unique(['user_id', 'badge_id', 'course_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('badge_id')->references('id')->on('badges')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
    }
};
