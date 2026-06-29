<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // course_id null  ->  personal calendar event (owned by the user)
            $table->foreignUuid('course_id')->nullable()->constrained('courses')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('event_type')->default('event');

            $table->boolean('all_day')->default(false);
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();

            // Simple recurrence (no external RRULE library)
            $table->string('recurrence_freq')->default('none'); // none|daily|weekly|monthly|yearly
            $table->unsignedInteger('recurrence_interval')->default(1);
            $table->date('recurrence_until')->nullable();

            $table->string('color')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['course_id', 'start_at']);
            $table->index(['user_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
