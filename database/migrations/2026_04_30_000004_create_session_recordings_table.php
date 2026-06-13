<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_session_recordings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('video_sessions')->onDelete('cascade');
            $table->string('s3_key');
            $table->integer('duration')->nullable(); // in seconds
            $table->integer('size')->nullable(); // in bytes
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamps();

            $table->index('session_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_session_recordings');
    }
    // NOTE: original revision mistakenly created `session_recordings` with an FK to a
    // non-existent `sessions` table; corrected here to match the SessionRecording model.
};
