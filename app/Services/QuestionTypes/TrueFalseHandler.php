<?php

namespace App\Services\QuestionTypes;

/**
 * True/False Question Handler
 * Simplest question type - exactly 2 answers: True or False
 */
class TrueFalseHandler extends QuestionTypeHandler
{
    public function getValidationRules(): array
    {
        return [
            'type'          => 'required|in:true_false',
            'question_text' => 'required|string|min:3',
            'correct_answer'=> 'required|in:True,False',
            'default_mark'  => 'numeric|min:0',
            'penalty'       => 'numeric|min:0|max:1',
        ];
    }

    public function processQuestionData(array $data): array
    {
        // Ensure correct_answer is capitalized properly
        if (!empty($data['correct_answer'])) {
            $data['correct_answer'] = ucfirst(strtolower($data['correct_answer']));
        }

        return $data;
    }

    public function gradeResponse(array $response): array
    {
        $studentAnswer = $response['response_text'] ?? $response['answer_text'] ?? null;
        $correct = $this->question->correct_answer;

        // Normalize for comparison
        $studentAnswer = $studentAnswer ? ucfirst(strtolower($studentAnswer)) : null;

        if ($studentAnswer === $correct) {
            return [
                'marks_awarded' => $this->question->default_mark,
                'is_correct'    => true,
                'feedback'      => "Correct! The answer is $correct.",
                'auto_graded'   => true,
            ];
        }

        return [
            'marks_awarded' => 0,
            'is_correct'    => false,
            'feedback'      => "Incorrect. The correct answer is $correct, not $studentAnswer.",
            'auto_graded'   => true,
        ];
    }

    public function isValidResponse(array $response): bool
    {
        $answer = $response['response_text'] ?? $response['answer_text'] ?? null;
        return !empty($answer);
    }

    public function getFeedback(array $response): ?string
    {
        $grading = $this->gradeResponse($response);
        return $grading['feedback'] ?? null;
    }
}
