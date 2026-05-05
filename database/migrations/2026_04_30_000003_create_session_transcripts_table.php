<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_session_transcripts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('video_sessions')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->text('text');
            $table->json('segments');
            $table->string('speaker_name');
            $table->timestamp('timestamp');
            $table->timestamps();

            $table->index(['session_id', 'timestamp']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_session_transcripts');
    }
};
