<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('practical_submissions', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
            $table->boolean('auto_submitted')->default(false)->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('practical_submissions', function (Blueprint $table) {
            $table->dropColumn(['started_at', 'auto_submitted']);
        });
    }
};
