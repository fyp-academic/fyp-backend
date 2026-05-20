<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_login_sessions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('device_type');
        });
    }

    public function down(): void
    {
        Schema::table('learner_login_sessions', function (Blueprint $table) {
            $table->dropColumn('ip_address');
        });
    }
};
