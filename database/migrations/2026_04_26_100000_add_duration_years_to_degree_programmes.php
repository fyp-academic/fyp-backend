<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('degree_programmes', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_years')->default(4)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('degree_programmes', function (Blueprint $table) {
            $table->dropColumn('duration_years');
        });
    }
};
