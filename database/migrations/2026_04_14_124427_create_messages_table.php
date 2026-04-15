<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id')->index();
            $table->uuid('sender_id')->index();
            $table->string('sender_name')->nullable();
            $table->text('content')->nullable();
            $table->timestamp('timestamp')->useCurrent();
            $table->boolean('read')->default(false);
            // file / image attachment
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_type', 60)->nullable();         // mime type
            $table->unsignedBigInteger('attachment_size')->nullable(); // bytes
            // emoji reactions stored as JSON: {"👍":["user1","user2"], "❤️":["user3"]}
            $table->json('reactions')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
