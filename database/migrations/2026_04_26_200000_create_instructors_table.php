<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Personal Information
            $table->string('full_name');
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('national_id')->nullable()->comment('National ID or Passport Number');
            $table->string('profile_photo')->nullable();

            // Employment Information
            $table->string('staff_id')->unique()->comment('University-issued Staff ID');
            $table->enum('employment_type', ['full-time', 'part-time', 'visiting'])->default('full-time');
            $table->enum('academic_rank', [
                'assistant_lecturer',
                'lecturer',
                'senior_lecturer',
                'associate_professor',
                'professor',
                'tutorial_assistant',
                'graduate_assistant'
            ])->nullable();
            $table->uuid('college_id')->nullable()->foreign('college_id')->references('id')->on('colleges')->nullOnDelete();
            $table->date('date_of_employment')->nullable();

            // Qualification Details
            $table->string('highest_qualification')->nullable()->comment('e.g., MSc, PhD');
            $table->string('field_of_specialization')->nullable();
            $table->string('awarding_institution')->nullable();
            $table->year('year_of_graduation')->nullable();

            // Additional Info
            $table->text('bio')->nullable()->comment('Short description');
            $table->string('office_location')->nullable();
            $table->string('office_hours')->nullable();

            // System Access
            $table->enum('account_status', ['active', 'inactive'])->default('active');

            $table->timestamps();

            // Indexes
            $table->index('staff_id');
            $table->index('college_id');
            $table->index('academic_rank');
        });

        // Pivot table for instructor-degree programme assignments
        Schema::create('instructor_degree_programme', function (Blueprint $table) {
            $table->id();
            $table->uuid('instructor_id')->foreign('instructor_id')->references('id')->on('instructors')->cascadeOnDelete();
            $table->uuid('degree_programme_id')->foreign('degree_programme_id')->references('id')->on('degree_programmes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['instructor_id', 'degree_programme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_degree_programme');
        Schema::dropIfExists('instructors');
    }
};
