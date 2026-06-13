<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flags attempts that were closed by the time limit / close window rather
     * than submitted in time by the student (client auto-submit or the
     * quizzes:expire-attempts scheduler). Lets instructors see late finishes.
     */
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->boolean('auto_submitted')->default(false)->after('time_spent');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('auto_submitted');
        });
    }
};
