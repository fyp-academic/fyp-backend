<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add support for all question types with proper validation and grading.
     * This migration enhances the schema to support:
     * - Matching questions (pair validation)
     * - Numerical questions (tolerance/units)
     * - Short answer (correct answer variation)
     * - Drag & drop (zones, draggables, configuration)
     * - Multiple answer selection (array of answer_ids)
     * - Complex response data (JSON structure for matching/drag_drop)
     * - Grading tracking (is_correct, auto_graded, graded_at)
     */
    public function up(): void
    {
        // ===== QUIZ_QUESTIONS TABLE ENHANCEMENTS =====
        Schema::table('quiz_questions', function (Blueprint $table) {
            // For numerical questions
            if (!Schema::hasColumn('quiz_questions', 'tolerance_type')) {
                $table->string('tolerance_type', 20)->nullable()->after('multiple_answers')
                    ->comment('relative, nominal, geometric for numerical'); // relative | nominal | geometric
            }
            if (!Schema::hasColumn('quiz_questions', 'tolerance_value')) {
                $table->decimal('tolerance_value', 6, 4)->nullable()->after('tolerance_type')
                    ->comment('Tolerance for numerical questions');
            }
            if (!Schema::hasColumn('quiz_questions', 'unit_handling')) {
                $table->string('unit_handling', 20)->nullable()->after('tolerance_value')
                    ->comment('Unit handling for numerical: no_units, optional_units, required_units');
            }
            if (!Schema::hasColumn('quiz_questions', 'units')) {
                $table->json('units')->nullable()->after('unit_handling')
                    ->comment('Allowed units for numerical: [{unit: "m", multiplier: 1}]');
            }

            // For short answer (variation support)
            if (!Schema::hasColumn('quiz_questions', 'case_sensitive')) {
                $table->boolean('case_sensitive')->default(false)->after('correct_answer')
                    ->comment('Case sensitive matching for short answer');
            }
            if (!Schema::hasColumn('quiz_questions', 'use_fuzzy_matching')) {
                $table->boolean('use_fuzzy_matching')->default(false)->after('case_sensitive')
                    ->comment('Enable fuzzy/similarity matching for short answer');
            }

            // For drag and drop
            if (!Schema::hasColumn('quiz_questions', 'drag_drop_config')) {
                $table->json('drag_drop_config')->nullable()->after('matching_pairs')
                    ->comment('Configuration for drag & drop: {zones: [], draggables: [], type: "image|text"}');
            }
            if (!Schema::hasColumn('quiz_questions', 'background_image')) {
                $table->string('background_image')->nullable()->after('drag_drop_config')
                    ->comment('Background image URL for drag_drop_markers');
            }

            // For type consistency
            if (!Schema::hasColumn('quiz_questions', 'grade_method')) {
                $table->string('grade_method', 20)->default('highest')->after('default_mark')
                    ->comment('highest, average, sum for multiple answers');
            }

            // Validation & metadata
            if (!Schema::hasColumn('quiz_questions', 'requires_manual_grading')) {
                $table->boolean('requires_manual_grading')->default(false)->after('use_fuzzy_matching')
                    ->comment('Whether this question type needs human grading');
            }
            if (!Schema::hasColumn('quiz_questions', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('requires_manual_grading')
                    ->comment('Soft delete / deactivation');
            }
        });

        // ===== QUIZ_ANSWERS TABLE ENHANCEMENTS =====
        Schema::table('quiz_answers', function (Blueprint $table) {
            // For matching questions
            if (!Schema::hasColumn('quiz_answers', 'match_group')) {
                $table->uuid('match_group')->nullable()->after('question_id')
                    ->comment('For matching: groups questions with their answer options');
            }
            if (!Schema::hasColumn('quiz_answers', 'answer_type')) {
                $table->string('answer_type', 20)->default('text')->after('sort_order')
                    ->comment('text, image, audio, formula');
            }
            if (!Schema::hasColumn('quiz_answers', 'answer_image_url')) {
                $table->string('answer_image_url')->nullable()->after('answer_type')
                    ->comment('For image-based answers');
            }
            if (!Schema::hasColumn('quiz_answers', 'min_value')) {
                $table->decimal('min_value', 10, 4)->nullable()->after('grade_fraction')
                    ->comment('For numerical answer range');
            }
            if (!Schema::hasColumn('quiz_answers', 'max_value')) {
                $table->decimal('max_value', 10, 4)->nullable()->after('min_value')
                    ->comment('For numerical answer range');
            }
        });

        // ===== QUIZ_ATTEMPT_RESPONSES TABLE ENHANCEMENTS =====
        Schema::table('quiz_attempt_responses', function (Blueprint $table) {
            // Complex response data (JSON for structured responses)
            if (!Schema::hasColumn('quiz_attempt_responses', 'response_data')) {
                $table->json('response_data')->nullable()->after('response_text')
                    ->comment('Structured response data: matching pairs, coordinates, etc.');
            }

            // Grading tracking
            if (!Schema::hasColumn('quiz_attempt_responses', 'is_correct')) {
                $table->boolean('is_correct')->nullable()->after('marks_awarded')
                    ->comment('Whether answer is correct (true/false/null for partial)');
            }
            if (!Schema::hasColumn('quiz_attempt_responses', 'auto_graded')) {
                $table->boolean('auto_graded')->default(false)->after('is_correct')
                    ->comment('Whether this was auto-graded (vs manual)');
            }
            if (!Schema::hasColumn('quiz_attempt_responses', 'graded_at')) {
                $table->timestamp('graded_at')->nullable()->after('auto_graded')
                    ->comment('When this response was graded');
            }
            if (!Schema::hasColumn('quiz_attempt_responses', 'graded_by')) {
                $table->uuid('graded_by')->nullable()->after('graded_at')
                    ->comment('User ID of manual grader (null for auto-grading)');
            }

            // Audit trail
            if (!Schema::hasColumn('quiz_attempt_responses', 'response_time')) {
                $table->integer('response_time')->nullable()->after('graded_by')
                    ->comment('Time spent on this question in seconds');
            }
            if (!Schema::hasColumn('quiz_attempt_responses', 'attempts')) {
                $table->integer('attempts')->default(1)->after('response_time')
                    ->comment('Number of answer changes before submission');
            }

            // Indices for common queries
            $table->index(['attempt_id', 'auto_graded']);
            $table->index(['question_id', 'auto_graded']);
            $table->index(['graded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $dropColumns = [
                'tolerance_type', 'tolerance_value', 'unit_handling', 'units',
                'case_sensitive', 'use_fuzzy_matching', 'drag_drop_config',
                'background_image', 'grade_method', 'requires_manual_grading', 'is_active'
            ];
            foreach ($dropColumns as $col) {
                if (Schema::hasColumn('quiz_questions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('quiz_answers', function (Blueprint $table) {
            $dropColumns = [
                'match_group', 'answer_type', 'answer_image_url',
                'min_value', 'max_value'
            ];
            foreach ($dropColumns as $col) {
                if (Schema::hasColumn('quiz_answers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('quiz_attempt_responses', function (Blueprint $table) {
            $dropColumns = [
                'response_data', 'is_correct', 'auto_graded', 'graded_at',
                'graded_by', 'response_time', 'attempts'
            ];
            foreach ($dropColumns as $col) {
                if (Schema::hasColumn('quiz_attempt_responses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
