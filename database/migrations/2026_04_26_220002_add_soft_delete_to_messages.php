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
        Schema::table('messages', function (Blueprint $table) {
            // Soft delete fields
            $table->timestamp('deleted_at')->nullable()->after('reactions');
            $table->uuid('deleted_by')->nullable()->after('deleted_at');
            $table->string('deletion_type', 20)->nullable()->after('deleted_by'); // 'me' or 'everyone'
            $table->text('original_content')->nullable()->after('deletion_type'); // for audit

            // Threading support (for Q&A, threaded discussions)
            $table->uuid('parent_id')->nullable()->after('conversation_id')->index();

            // Message type
            $table->string('message_type', 20)->default('text')->after('content'); // text, question, announcement, resource

            // Pin status (for important messages)
            $table->boolean('is_pinned')->default(false)->after('message_type');
            $table->uuid('pinned_by')->nullable()->after('is_pinned');
            $table->timestamp('pinned_at')->nullable()->after('pinned_by');

            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('parent_id')->references('id')->on('messages')->nullOnDelete();
            $table->foreign('pinned_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['pinned_by']);
            $table->dropColumn(['deleted_at', 'deleted_by', 'deletion_type', 'original_content', 'parent_id', 'message_type', 'is_pinned', 'pinned_by', 'pinned_at']);
        });
    }
};
