<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corrective migration for databases that already ran the original (buggy)
     * 2026_04_30_000004 revision, which created the recordings table as
     * `session_recordings` instead of `video_session_recordings` (the name the
     * SessionRecording model expects). Rename it so recording persistence works.
     * No-op on fresh installs where the corrected migration already made the
     * right table.
     */
    public function up(): void
    {
        if (Schema::hasTable('session_recordings') && ! Schema::hasTable('video_session_recordings')) {
            Schema::rename('session_recordings', 'video_session_recordings');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('video_session_recordings') && ! Schema::hasTable('session_recordings')) {
            Schema::rename('video_session_recordings', 'session_recordings');
        }
    }
};
