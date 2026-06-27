<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_post_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('post_id')->index();
            $table->uuid('user_id')->index();
            $table->tinyInteger('value');                // 1 = like, -1 = dislike
            $table->timestamps();

            $table->unique(['post_id', 'user_id']);
            $table->foreign('post_id')->references('id')->on('forum_posts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_post_reactions');
    }
};
