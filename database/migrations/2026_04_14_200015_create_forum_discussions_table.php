<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_discussions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->index();
            $table->uuid('course_id')->index();
            $table->uuid('user_id')->index();
            $table->string('title');
            $table->boolean('pinned')->default(false);
            $table->boolean('locked')->default(false);
            $table->unsignedInteger('post_count')->default(0);
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_discussions');
    }
};
