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
        Schema::table('users', function (Blueprint $table) {
            $table->string('initials', 5)->nullable()->after('name');
            $table->string('last_access')->nullable()->after('language');
            $table->unsignedInteger('enrolled_courses')->default(0)->after('last_access');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['initials', 'last_access', 'enrolled_courses']);
        });
    }
};
