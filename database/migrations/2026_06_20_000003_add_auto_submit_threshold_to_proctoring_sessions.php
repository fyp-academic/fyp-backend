<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            $table->unsignedSmallInteger('auto_submit_threshold')->default(5)->after('violation_count');
        });
    }

    public function down(): void
    {
        Schema::table('proctoring_sessions', function (Blueprint $table) {
            $table->dropColumn('auto_submit_threshold');
        });
    }
};
