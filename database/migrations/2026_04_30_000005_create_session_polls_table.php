<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polls table
        Schema::create('video_session_polls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('video_sessions')->onDelete('cascade');
            $table->text('question');
            $table->json('options');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_multiple_choice')->default(false);
            $table->foreignUuid('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            
            $table->index('session_id');
            $table->index('is_active');
        });

        // Poll votes table
        Schema::create('video_session_poll_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('poll_id')->constrained('video_session_polls')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->integer('option_index');
            $table->timestamps();
            
            $table->unique(['poll_id', 'user_id']);
            $table->index('poll_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_session_poll_votes');
        Schema::dropIfExists('video_session_polls');
    }
};
