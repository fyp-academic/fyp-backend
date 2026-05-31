<?php

namespace App\Services\QuestionTypes;

/**
 * Essay Question Handler
 * Requires manual grading - no auto-grading
 * Stores student's free-text response for instructor review
 */
class EssayHandler extends QuestionTypeHandler
{
    public function getValidationRules(): array
    {
        return [
            'type'          => 'required|in:essay',
            'question_text' => 'required|string|min:3',
            'default_mark'  => 'numeric|min:0',
        ];
    }

    public function processQuestionData(array $data): array
    {
        // Essays always require manual grading
        $data['requires_manual_grading'] = true;
        return $data;
    }

    public function gradeResponse(array $response): array
    {
        // Essays are never auto-graded
        return [
            'marks_awarded' => null, // Will be set by instructor
            'is_correct'    => null, // Will be determined by instructor
            'feedback'      => 'This essay response requires manual grading by an instructor.',
            'auto_graded'   => false,
        ];
    }

    public function isValidResponse(array $response): bool
    {
        // Even empty essays are valid - they'll be graded as 0 by instructor
        return true;
    }

    public function getFeedback(array $response): ?string
    {
        return 'Your essay has been submitted and will be reviewed by your instructor.';
    }

    public function requiresManualGrading(): bool
    {
        return true;
    }
}
