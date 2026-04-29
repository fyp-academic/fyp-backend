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
        Schema::create('notification_digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'push']);
            $table->enum('frequency', ['daily', 'weekly']);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_send_at');
            $table->json('pending_ids')->nullable()->comment('array of notification IDs to batch');
            $table->timestamps();

            $table->index(['user_id', 'channel', 'frequency']);
            $table->index('next_send_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_digests');
    }
};
