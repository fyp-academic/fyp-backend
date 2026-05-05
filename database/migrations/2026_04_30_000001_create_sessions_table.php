<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->foreignUuid('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignUuid('instructor_id')->constrained('users')->onDelete('cascade');
            $table->string('room_id')->unique();
            $table->enum('status', ['scheduled', 'live', 'ended'])->default('scheduled');
            $table->timestamp('scheduled_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration')->nullable(); // in minutes
            $table->integer('max_participants')->default(100);
            $table->string('password')->nullable();
            $table->boolean('recording_enabled')->default(false);
            $table->boolean('chat_enabled')->default(true);
            $table->boolean('raise_hand_enabled')->default(true);
            $table->boolean('waiting_room')->default(false);
            $table->json('breakout_rooms')->nullable();
            $table->boolean('screen_share_allowed')->default(true);
            $table->boolean('start_muted')->default(false);
            $table->boolean('start_video_off')->default(false);
            $table->boolean('ai_transcription')->default(false);
            $table->string('recording_url')->nullable();
            $table->text('ai_summary')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'status']);
            $table->index(['instructor_id', 'status']);
            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_sessions');
    }
};
