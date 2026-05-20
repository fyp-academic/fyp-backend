<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_session_participants', function (Blueprint $table) {
            $table->smallInteger('join_punctuality_minutes')->nullable()->after('attendance_score');
            // negative = arrived early, 0 = on time, positive = minutes late
            $table->unsignedSmallInteger('poll_responses_count')->default(0)->after('join_punctuality_minutes');
            $table->unsignedInteger('participation_duration_seconds')->default(0)->after('poll_responses_count');
            // computed from left_at - joined_at when participant leaves
        });
    }

    public function down(): void
    {
        Schema::table('video_session_participants', function (Blueprint $table) {
            $table->dropColumn(['join_punctuality_minutes', 'poll_responses_count', 'participation_duration_seconds']);
        });
    }
};
