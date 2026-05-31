<?php

namespace App\Services\QuestionTypes;

/**
 * Matching Question Handler
 * Supports:
 * - Multiple question-answer pairs
 * - Partial credit
 * - Answer shuffling
 */
class MatchingHandler extends QuestionTypeHandler
{
    public function getValidationRules(): array
    {
        return [
            'type'              => 'required|in:matching',
            'question_text'     => 'required|string|min:3',
            'matching_pairs'    => 'required|array|min:2',
            'matching_pairs.*.question' => 'required|string|min:1',
            'matching_pairs.*.answer'   => 'required|string|min:1',
            'default_mark'      => 'numeric|min:0',
            'shuffle_answers'   => 'boolean',
        ];
    }

    public function processQuestionData(array $data): array
    {
        // Validate and normalize matching pairs
        if (isset($data['matching_pairs'])) {
            $data['matching_pairs'] = $this->validateAndNormalizePairs($data['matching_pairs']);
        }

        return $data;
    }

    public function gradeResponse(array $response): array
    {
        $studentMatches = $response['response_data'] ?? $response['response_text'] ?? null;

        if (empty($studentMatches)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'You must provide matches for all question items.',
                'auto_graded'   => false, // Manual review
            ];
        }

        // Parse student responses (expected format: {question_id: answer_id})
        $studentPairs = is_string($studentMatches) ? json_decode($studentMatches, true) : $studentMatches;
        if (!is_array($studentPairs)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'Invalid matching format.',
                'auto_graded'   => false,
            ];
        }

        $correctPairs = $this->question->matching_pairs ?? [];
        $totalPairs = count($correctPairs);

        if ($totalPairs === 0) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'Question has no matching pairs defined.',
                'auto_graded'   => false,
            ];
        }

        $correctCount = 0;

        // Check each pair
        foreach ($correctPairs as $index => $pair) {
            $studentAnswer = $studentPairs[$pair['question']] ?? null;
            if ($studentAnswer === $pair['answer']) {
                $correctCount++;
            }
        }

        // Calculate score (partial credit based on correct pairs)
        $correctPercentage = $correctCount / $totalPairs;
        $marks = $this->question->default_mark * $correctPercentage;

        if ($correctCount === $totalPairs) {
            return [
                'marks_awarded' => $this->question->default_mark,
                'is_correct'    => true,
                'feedback'      => "Perfect! All $totalPairs matches are correct.",
                'auto_graded'   => true,
            ];
        }

        return [
            'marks_awarded' => round($marks, 2),
            'is_correct'    => false,
            'feedback'      => "You matched $correctCount out of $totalPairs items correctly.",
            'auto_graded'   => true,
        ];
    }

    public function isValidResponse(array $response): bool
    {
        $pairs = $response['response_data'] ?? $response['response_text'] ?? null;
        return !empty($pairs);
    }

    public function getFeedback(array $response): ?string
    {
        $grading = $this->gradeResponse($response);
        return $grading['feedback'] ?? null;
    }

    /**
     * Validate and normalize matching pairs structure
     */
    private function validateAndNormalizePairs(array $pairs): array
    {
        $normalized = [];

        foreach ($pairs as $pair) {
            if (!isset($pair['question']) || !isset($pair['answer'])) {
                throw new \InvalidArgumentException('Each matching pair must have "question" and "answer" fields.');
            }

            if (empty(trim($pair['question'])) || empty(trim($pair['answer']))) {
                throw new \InvalidArgumentException('Matching pair question and answer cannot be empty.');
            }

            $normalized[] = [
                'question' => trim($pair['question']),
                'answer'   => trim($pair['answer']),
            ];
        }

        return $normalized;
    }
}
