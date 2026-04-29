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
        // Drop existing table and recreate with new schema
        Schema::dropIfExists('notifications');

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80)->comment('e.g. assignment_due, grade_released, etc.');
            $table->enum('channel', ['in_app', 'email', 'push', 'sms']);
            $table->string('title', 255);
            $table->text('body')->nullable();
            $table->json('payload')->nullable()->comment('contextual data');
            $table->string('dedup_key', 150)->nullable()->unique()->comment('idempotency guard');
            $table->enum('status', ['pending', 'sent', 'delivered', 'read', 'failed'])->default('pending');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failed_reason')->nullable();
            $table->tinyInteger('retry_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'status'], 'idx_user_status');
            $table->index(['type', 'created_at'], 'idx_type_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');

        // Restore original structure (approximate)
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('title');
            $table->text('message');
            $table->timestamp('timestamp')->useCurrent();
            $table->boolean('read')->default(false);
            $table->string('type', 40)->default('info');
            $table->string('action_url')->nullable();
            $table->json('data')->nullable();
        });
    }
};
