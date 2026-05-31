<?php

namespace App\Services\QuestionTypes;

use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Validator;

/**
 * Base class for question type handlers.
 * Each question type implements specific validation, storage, and grading logic.
 */
abstract class QuestionTypeHandler
{
    protected QuizQuestion $question;

    public function __construct(QuizQuestion $question)
    {
        $this->question = $question;
    }

    /**
     * Validate question data before creation/update.
     *
     * @param array $data
     * @return array Validation rules specific to this question type
     */
    abstract public function getValidationRules(): array;

    /**
     * Process and normalize question data after validation.
     *
     * @param array $data
     * @return array Processed data
     */
    abstract public function processQuestionData(array $data): array;

    /**
     * Grade a student response for this question type.
     *
     * @param array $response Student response data
     * @return array Contains: marks_awarded (float), is_correct (bool), feedback (string), auto_graded (bool)
     */
    abstract public function gradeResponse(array $response): array;

    /**
     * Validate a student response before grading.
     *
     * @param array $response
     * @return bool
     */
    abstract public function isValidResponse(array $response): bool;

    /**
     * Check if this question type requires manual grading.
     *
     * @return bool
     */
    public function requiresManualGrading(): bool
    {
        return $this->question->requires_manual_grading ?? false;
    }

    /**
     * Get feedback for student response.
     *
     * @param array $response
     * @return string|null
     */
    abstract public function getFeedback(array $response): ?string;
}
