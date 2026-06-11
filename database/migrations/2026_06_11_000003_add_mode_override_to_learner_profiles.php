<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_profiles', function (Blueprint $table) {
            $table->string('adaptation_mode_override', 30)->nullable()->after('primary_profile')
                ->comment('Instructor-pinned presentation mode; overrides AI selection when set.');
        });
    }

    public function down(): void
    {
        Schema::table('learner_profiles', function (Blueprint $table) {
            $table->dropColumn('adaptation_mode_override');
        });
    }
};
