<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proctoring_violations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('session_id');
            $table->string('type');                    // tab_switch | fullscreen_exit | copy_attempt | paste_attempt | right_click | keyboard_shortcut | no_face_detected | multiple_faces | looking_away | phone_detected | browser_blur | ai_content_detected
            $table->json('metadata')->nullable();
            $table->string('action_taken');            // warn | final_warning | force_submit
            $table->integer('warning_count_at_time');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('proctoring_sessions')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proctoring_violations');
    }
};
