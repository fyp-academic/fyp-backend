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
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_user_id')->index();
            $table->uuid('participant_user_id')->index();
            $table->string('participant_name')->nullable();
            $table->string('participant_role', 40)->nullable();
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_time')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->uuid('course_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['owner_user_id', 'participant_user_id']);
            $table->foreign('owner_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('participant_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
