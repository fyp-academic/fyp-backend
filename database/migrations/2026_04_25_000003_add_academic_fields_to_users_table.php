<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_number', 30)->nullable()->unique()->after('role');
            $table->uuid('degree_programme_id')->nullable()->index()->after('registration_number');
            $table->unsignedTinyInteger('year_of_study')->nullable()->after('degree_programme_id');
            $table->string('education_level', 30)->nullable()->after('year_of_study');
            $table->string('nationality', 100)->nullable()->after('education_level');

            $table->foreign('degree_programme_id')->references('id')->on('degree_programmes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['degree_programme_id']);
            $table->dropColumn([
                'registration_number',
                'degree_programme_id',
                'year_of_study',
                'education_level',
                'nationality',
            ]);
        });
    }
};
