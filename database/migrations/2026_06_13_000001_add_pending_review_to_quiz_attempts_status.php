<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'pending_review' to the quiz_attempts.status enum so manually-graded
     * submissions (essay / short-answer) can be persisted and later reviewed.
     *
     * Uses the portable schema builder so it works on both SQLite (dev — enum is
     * emulated with a CHECK constraint that the table rebuild replaces) and MySQL.
     */
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->enum('status', ['in_progress', 'submitted', 'pending_review', 'graded'])
                ->default('in_progress')
                ->change();
        });
    }

    public function down(): void
    {
        // Demote any pending_review rows so the value fits the narrower enum.
        DB::table('quiz_attempts')->where('status', 'pending_review')->update(['status' => 'submitted']);

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->enum('status', ['in_progress', 'submitted', 'graded'])
                ->default('in_progress')
                ->change();
        });
    }
};
