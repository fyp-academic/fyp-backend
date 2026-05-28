<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('adaptation_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id');
            $table->uuid('chunk_id');
            $table->text('adapted_text');
            $table->text('original_text');
            $table->jsonb('profile_snapshot');
            $table->jsonb('instructor_settings_snapshot');
            $table->boolean('flagged')->default(false);
            $table->uuid('flagged_by')->nullable();
            $table->string('feedback_rating', 20)->nullable(); // positive | negative
            $table->string('feedback_complexity', 20)->nullable(); // too_simple | just_right | too_complex
            $table->timestamps();

            $table->index(['student_id', 'chunk_id']);
            $table->index(['flagged', 'created_at']);
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('chunk_id')->references('id')->on('content_chunks')->onDelete('cascade');
            $table->foreign('flagged_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adaptation_log');
    }
};
