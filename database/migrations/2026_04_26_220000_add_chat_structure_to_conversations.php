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
        Schema::table('conversations', function (Blueprint $table) {
            // Chat type: direct, course, programme
            $table->string('type', 20)->default('direct')->after('id')->index();
            // For course chats: link to course
            $table->uuid('programme_id')->nullable()->after('course_id')->index();
            // Conversation title (for group chats)
            $table->string('title')->nullable()->after('type');
            // Instructor moderation flag
            $table->boolean('is_moderated')->default(false)->after('title');
            // Lock/archived status
            $table->boolean('is_locked')->default(false)->after('is_moderated');
            // Timestamps already exist, add deleted_at for soft delete of conversation
            $table->softDeletes()->after('updated_at');

            $table->foreign('programme_id')->references('id')->on('degree_programmes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['programme_id']);
            $table->dropColumn(['type', 'programme_id', 'title', 'is_moderated', 'is_locked', 'deleted_at']);
        });
    }
};
