<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('behavioral_signals', function (Blueprint $table) {
            $table->unsignedSmallInteger('consecutive_active_days')->default(0)->after('navigation_pattern');
            $table->decimal('avg_inactivity_gap_days', 5, 2)->default(0)->after('consecutive_active_days');
            $table->unsignedSmallInteger('bounce_session_count')->default(0)->after('avg_inactivity_gap_days');
            $table->unsignedTinyInteger('peak_hour_of_day')->nullable()->after('bounce_session_count'); // 0-23
            $table->string('device_type_primary', 20)->nullable()->after('peak_hour_of_day');          // desktop | mobile | tablet
            $table->unsignedSmallInteger('material_open_count')->default(0)->after('device_type_primary');
            $table->decimal('avg_video_watch_percent', 5, 2)->nullable()->after('material_open_count');
        });
    }

    public function down(): void
    {
        Schema::table('behavioral_signals', function (Blueprint $table) {
            $table->dropColumn([
                'consecutive_active_days',
                'avg_inactivity_gap_days',
                'bounce_session_count',
                'peak_hour_of_day',
                'device_type_primary',
                'material_open_count',
                'avg_video_watch_percent',
            ]);
        });
    }
};
