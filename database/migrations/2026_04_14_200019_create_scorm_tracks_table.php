<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scorm_tracks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->index();
            $table->uuid('student_id')->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->string('element', 200);                             // cmi.core.lesson_status etc.
            $table->text('value')->nullable();
            $table->string('status', 30)->default('not_attempted');    // not_attempted | incomplete | completed | passed | failed
            $table->decimal('score_raw', 6, 2)->nullable();
            $table->decimal('score_max', 6, 2)->nullable();
            $table->string('total_time', 20)->nullable();               // ISO 8601 duration PT1H30M
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('student_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scorm_tracks');
    }
};
