<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('short_name', 50)->nullable();
            $table->text('description')->nullable();
            $table->uuid('category_id')->nullable()->index();
            $table->string('category_name')->nullable();
            $table->uuid('instructor_id')->nullable()->index();
            $table->string('instructor_name')->nullable();
            $table->unsignedInteger('enrolled_students')->default(0);
            $table->string('status', 30)->default('draft')->index();     // draft | active | archived
            $table->string('visibility', 20)->default('shown');          // shown | hidden
            $table->string('format', 30)->default('topics');             // topics | weeks | social
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('language', 40)->default('English');
            $table->json('tags')->nullable();
            $table->unsignedInteger('max_students')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('instructor_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
