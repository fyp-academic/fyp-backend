<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('discussion_id')->index();
            $table->uuid('user_id')->index();
            $table->uuid('parent_id')->nullable()->index();             // NULL = root post, otherwise a reply
            $table->string('subject')->nullable();
            $table->longText('content');
            $table->string('attachment_path')->nullable();
            $table->timestamps();

            $table->foreign('discussion_id')->references('id')->on('forum_discussions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('forum_posts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_posts');
    }
};
