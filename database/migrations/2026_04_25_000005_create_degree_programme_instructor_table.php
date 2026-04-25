<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('degree_programme_instructor', function (Blueprint $table) {
            $table->uuid('degree_programme_id');
            $table->uuid('instructor_id');
            $table->timestamps();

            $table->primary(['degree_programme_id', 'instructor_id']);
            $table->foreign('degree_programme_id')->references('id')->on('degree_programmes')->cascadeOnDelete();
            $table->foreign('instructor_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('degree_programme_instructor');
    }
};
