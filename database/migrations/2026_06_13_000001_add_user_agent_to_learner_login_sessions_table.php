<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_login_sessions', function (Blueprint $table) {
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('browser', 60)->nullable()->after('user_agent');
            $table->string('os', 60)->nullable()->after('browser');
        });
    }

    public function down(): void
    {
        Schema::table('learner_login_sessions', function (Blueprint $table) {
            $table->dropColumn(['user_agent', 'browser', 'os']);
        });
    }
};
