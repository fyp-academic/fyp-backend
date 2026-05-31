<?php

namespace App\Services\QuestionTypes;

use Levenshtein;

/**
 * Short Answer Question Handler
 * Supports:
 * - Exact answer matching
 * - Case-insensitive matching
 * - Fuzzy/similarity matching using Levenshtein distance
 */
class ShortAnswerHandler extends QuestionTypeHandler
{
    public function getValidationRules(): array
    {
        return [
            'type'                  => 'required|in:short_answer',
            'question_text'         => 'required|string|min:3',
            'correct_answer'        => 'required|string|min:1',
            'case_sensitive'        => 'boolean',
            'use_fuzzy_matching'    => 'boolean',
            'default_mark'          => 'numeric|min:0',
        ];
    }

    public function processQuestionData(array $data): array
    {
        // Normalize boolean fields
        $data['case_sensitive'] = $data['case_sensitive'] ?? false;
        $data['use_fuzzy_matching'] = $data['use_fuzzy_matching'] ?? false;

        return $data;
    }

    public function gradeResponse(array $response): array
    {
        $studentAnswer = $response['response_text'] ?? '';
        $correctAnswers = $this->parseCorrectAnswers();

        if (empty($studentAnswer)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'Your answer cannot be empty.',
                'auto_graded'   => false, // Requires review
            ];
        }

        // Try to find a match
        $match = $this->findMatchingAnswer($studentAnswer, $correctAnswers);

        if ($match) {
            return [
                'marks_awarded' => $this->question->default_mark,
                'is_correct'    => true,
                'feedback'      => "Correct! Your answer \"$studentAnswer\" matches the expected answer.",
                'auto_graded'   => true,
            ];
        }

        // If fuzzy matching enabled and it's close, give partial credit
        if ($this->question->use_fuzzy_matching) {
            $similarity = $this->findSimilarAnswer($studentAnswer, $correctAnswers);
            if ($similarity > 0.7) { // 70% similarity threshold
                $partialMarks = $this->question->default_mark * $similarity;
                return [
                    'marks_awarded' => round($partialMarks, 2),
                    'is_correct'    => false,
                    'feedback'      => "Partially correct. Your answer \"$studentAnswer\" is similar to the expected answer, but may need review.",
                    'auto_graded'   => false, // Flag for instructor review
                ];
            }
        }

        return [
            'marks_awarded' => 0,
            'is_correct'    => false,
            'feedback'      => "Your answer \"$studentAnswer\" does not match the expected answer. This will be reviewed by the instructor.",
            'auto_graded'   => false, // Mark for manual review
        ];
    }

    public function isValidResponse(array $response): bool
    {
        return !empty(trim($response['response_text'] ?? ''));
    }

    public function getFeedback(array $response): ?string
    {
        $grading = $this->gradeResponse($response);
        return $grading['feedback'] ?? null;
    }

    /**
     * Parse correct answers - can be multiple separated by |
     */
    private function parseCorrectAnswers(): array
    {
        $answers = explode('|', $this->question->correct_answer ?? '');
        return array_map('trim', $answers);
    }

    /**
     * Check if student answer matches any correct answer
     */
    private function findMatchingAnswer(string $student, array $correct): bool
    {
        foreach ($correct as $expected) {
            if ($this->question->case_sensitive) {
                if ($student === $expected) {
                    return true;
                }
            } else {
                if (strtolower($student) === strtolower($expected)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Find similarity using Levenshtein distance
     * Returns 0-1 score
     */
    private function findSimilarAnswer(string $student, array $correct): float
    {
        $maxSimilarity = 0;

        foreach ($correct as $expected) {
            $compareStudent = $this->question->case_sensitive ? $student : strtolower($student);
            $compareExpected = $this->question->case_sensitive ? $expected : strtolower($expected);

            $maxLen = max(strlen($compareStudent), strlen($compareExpected));
            if ($maxLen === 0) {
                $similarity = 1.0;
            } else {
                $distance = levenshtein($compareStudent, $compareExpected);
                $similarity = 1 - ($distance / $maxLen);
            }

            if ($similarity > $maxSimilarity) {
                $maxSimilarity = $similarity;
            }
        }

        return $maxSimilarity;
    }
}
