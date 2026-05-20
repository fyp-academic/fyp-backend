<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emotional_signals', function (Blueprint $table) {
            $table->decimal('frustration_index', 5, 2)->default(0)->after('forum_questions_asked');
            // derived: failed_attempts + rapid_retries + sudden_inactivity
            $table->decimal('post_failure_inactivity_hours', 7, 2)->nullable()->after('frustration_index');
            // hours inactive after a score below threshold
            $table->decimal('forum_sentiment_avg', 4, 2)->nullable()->after('post_failure_inactivity_hours');
            // AI: -1.00 (very negative) to 1.00 (very positive)
            $table->decimal('notification_response_rate', 5, 2)->default(0)->after('forum_sentiment_avg');
            // % of notifications that were read within 24h
            $table->decimal('feedback_response_lag_hours', 7, 2)->nullable()->after('notification_response_rate');
            $table->decimal('voluntary_engagement_rate', 5, 2)->default(0)->after('feedback_response_lag_hours');
            $table->decimal('voluntary_engagement_delta', 6, 2)->nullable()->after('voluntary_engagement_rate');
            $table->boolean('badge_earned_this_week')->default(false)->after('voluntary_engagement_delta');
            $table->decimal('badge_response_delta', 6, 2)->nullable()->after('badge_earned_this_week');
        });
    }

    public function down(): void
    {
        Schema::table('emotional_signals', function (Blueprint $table) {
            $table->dropColumn([
                'frustration_index',
                'post_failure_inactivity_hours',
                'forum_sentiment_avg',
                'notification_response_rate',
                'feedback_response_lag_hours',
                'voluntary_engagement_rate',
                'voluntary_engagement_delta',
                'badge_earned_this_week',
                'badge_response_delta',
            ]);
        });
    }
};
