<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_degree_programme', function (Blueprint $table) {
            $table->uuid('course_id');
            $table->uuid('degree_programme_id');
            $table->timestamps();

            $table->primary(['course_id', 'degree_programme_id']);
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
            $table->foreign('degree_programme_id')->references('id')->on('degree_programmes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_degree_programme');
    }
};
