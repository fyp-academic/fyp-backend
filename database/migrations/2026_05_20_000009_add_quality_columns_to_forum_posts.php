<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->unsignedSmallInteger('likes_count')->default(0)->after('attachment_path');
            $table->decimal('quality_score', 3, 2)->nullable()->after('likes_count');
            // AI-computed 0.00-1.00 quality rating
            $table->string('sentiment', 20)->nullable()->after('quality_score');
            // positive | neutral | negative | frustrated | confused
            $table->unsignedTinyInteger('depth_level')->default(0)->after('sentiment');
            // 0 = root discussion post, 1 = reply, 2 = reply-to-reply, etc.
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn(['likes_count', 'quality_score', 'sentiment', 'depth_level']);
        });
    }
};
