<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('activity_id')->unique();                      // one template per activity
            $table->uuid('course_id')->index();
            $table->string('name');
            $table->longText('body_html')->nullable();
            $table->string('orientation', 15)->default('landscape');    // landscape | portrait
            $table->json('required_activities')->nullable();            // UUIDs of activities that must be completed
            $table->decimal('min_grade', 5, 2)->nullable();
            $table->unsignedSmallInteger('expiry_days')->nullable();    // 0 = never expires
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('activities')->cascadeOnDelete();
            $table->foreign('course_id')->references('id')->on('courses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_templates');
    }
};
