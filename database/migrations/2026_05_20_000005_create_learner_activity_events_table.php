<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_activity_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->uuid('course_id')->nullable()->index();
            $table->uuid('login_session_id')->nullable()->index();
            $table->string('event_type', 50);
            // content_view | video_play | video_pause | video_seek | video_complete
            // pdf_open | pdf_scroll | material_download | content_skip
            // quiz_start | quiz_submit | quiz_question_skip
            // forum_post | forum_reply | forum_view
            // activity_complete | login | logout
            // page_idle | tab_blur | tab_focus | search
            $table->string('resource_type', 40)->nullable();  // activity | material | forum_post | quiz | session
            $table->uuid('resource_id')->nullable();
            $table->decimal('value', 8, 2)->nullable();       // e.g. watch %, scroll %, duration seconds
            $table->json('metadata')->nullable();              // { playback_speed, seek_from, seek_to, word_count, ... }
            $table->string('device_type', 20)->default('desktop'); // desktop | mobile | tablet
            $table->timestamp('occurred_at');
            // No created_at/updated_at to keep insert speed high; occurred_at is the record time
            $table->timestamps();

            $table->index(['user_id', 'course_id', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index(['resource_type', 'resource_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_activity_events');
    }
};
