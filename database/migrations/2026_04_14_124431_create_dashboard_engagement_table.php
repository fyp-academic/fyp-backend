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
        Schema::create('dashboard_engagement', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('course_id')->index();
            $table->string('day_label', 20);                            // Mon | Tue | Wed …
            $table->unsignedInteger('active_students')->default(0);
            $table->unsignedInteger('submissions')->default(0);
            $table->date('week_of');
            $table->timestamps();

            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_engagement');
    }
};
