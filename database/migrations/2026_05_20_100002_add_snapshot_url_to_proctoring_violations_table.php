<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proctoring_violations', function (Blueprint $table) {
            $table->string('snapshot_url')->nullable()->after('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::table('proctoring_violations', function (Blueprint $table) {
            $table->dropColumn('snapshot_url');
        });
    }
};
