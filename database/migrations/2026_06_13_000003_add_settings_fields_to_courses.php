<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Course-settings fields the instructor authoring form already sends but the
     * backend never stored, so they were lost on save and blank on reload.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('id_number')->nullable()->after('short_name');
            $table->text('summary')->nullable()->after('description');
            $table->string('group_mode', 20)->nullable()->after('format');
            $table->boolean('self_enrollment')->default(false)->after('group_mode');
            $table->string('enrollment_key')->nullable()->after('self_enrollment');
            $table->date('enrollment_start_date')->nullable()->after('enrollment_key');
            $table->date('enrollment_end_date')->nullable()->after('enrollment_start_date');
            $table->string('grade_display_type', 20)->nullable()->after('enrollment_end_date');
            $table->integer('grade_passing_grade')->nullable()->after('grade_display_type');
            $table->boolean('completion_tracking')->default(true)->after('grade_passing_grade');
            $table->integer('max_upload_size')->nullable()->after('completion_tracking');
            $table->string('allowed_file_types')->nullable()->after('max_upload_size');
            $table->boolean('show_gradebook')->default(true)->after('allowed_file_types');
            $table->boolean('show_activity_reports')->default(false)->after('show_gradebook');
            $table->boolean('force_download')->default(false)->after('show_activity_reports');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn([
                'id_number', 'summary', 'group_mode', 'self_enrollment', 'enrollment_key',
                'enrollment_start_date', 'enrollment_end_date', 'grade_display_type',
                'grade_passing_grade', 'completion_tracking', 'max_upload_size',
                'allowed_file_types', 'show_gradebook', 'show_activity_reports', 'force_download',
            ]);
        });
    }
};
