<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->integer('dislikes_count')->default(0)->after('likes_count');
            // Whether this post's author is shown anonymously (discussion anonymity modes)
            $table->boolean('anonymous')->default(false)->after('dislikes_count');
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table) {
            $table->dropColumn(['dislikes_count', 'anonymous']);
        });
    }
};
