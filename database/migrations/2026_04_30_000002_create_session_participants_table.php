<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_session_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('video_sessions')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->boolean('mic_active')->default(false);
            $table->boolean('camera_active')->default(false);
            $table->integer('hands_raised')->default(0);
            $table->integer('chat_messages')->default(0);
            $table->integer('attendance_score')->default(0); // 0-100
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
            $table->index(['session_id', 'joined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_session_participants');
    }
};
