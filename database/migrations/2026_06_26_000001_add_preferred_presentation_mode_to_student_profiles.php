<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('preferred_presentation_mode', 30)->nullable()->after('preferred_modality')
                ->comment('Explicit student-chosen presentation mode; overrides instructor pin and AI selection when set.');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('preferred_presentation_mode');
        });
    }
};
