<?php

namespace App\Services\QuestionTypes;

use App\Models\QuizQuestion;

/**
 * Multiple Choice Question Handler
 * Supports:
 * - Single answer (default)
 * - Multiple answers (any subset marked correct)
 * - Answer shuffling
 * - Custom numbering (a,b,c / A,B,C / 1,2,3 / etc)
 * - Partial credit per answer
 */
class MultipleChoiceHandler extends QuestionTypeHandler
{
    public function getValidationRules(): array
    {
        return [
            'type'              => 'required|in:multiple_choice',
            'question_text'     => 'required|string|min:3',
            'default_mark'      => 'numeric|min:0',
            'shuffle_answers'   => 'boolean',
            'multiple_answers'  => 'boolean',
            'choice_numbering'  => 'nullable|in:none,a,b,c,A,B,C,i,ii,iii,I,II,III,1,2,3',
            'penalty'           => 'numeric|min:0|max:1',
        ];
    }

    public function processQuestionData(array $data): array
    {
        // Normalize choice numbering
        if (!empty($data['choice_numbering'])) {
            $data['choice_numbering'] = $this->normalizeChoiceNumbering($data['choice_numbering']);
        }

        return $data;
    }

    public function gradeResponse(array $response): array
    {
        $feedback = '';
        $marks = 0;
        $isCorrect = false;

        // Support both single answer_id and array of answer_ids
        $studentAnswerIds = $response['answer_id'] ?? [];
        if (!is_array($studentAnswerIds)) {
            $studentAnswerIds = $studentAnswerIds ? [$studentAnswerIds] : [];
        }

        // Get all answers for this question
        $answers = $this->question->answers;
        $correctAnswers = $answers->filter(fn($a) => $a->grade_fraction > 0);

        // Multiple answers mode
        if ($this->question->multiple_answers) {
            $correctIds = $correctAnswers->pluck('id')->toArray();
            $selectedCorrect = count(array_intersect($studentAnswerIds, $correctIds));
            $expectedCorrect = count($correctIds);

            if ($selectedCorrect === $expectedCorrect && count($studentAnswerIds) === $expectedCorrect) {
                // Perfect: selected all correct and no incorrect
                $marks = $this->question->default_mark;
                $isCorrect = true;
                $feedback = "Correct! You selected all the right answers.";
            } elseif ($selectedCorrect > 0) {
                // Partial credit
                $marks = ($selectedCorrect / $expectedCorrect) * $this->question->default_mark;
                $isCorrect = false;
                $feedback = "Partially correct. You got $selectedCorrect out of $expectedCorrect answers right.";
            } else {
                // No correct answers selected
                $marks = max(0, -($this->question->penalty ?? 0) * $this->question->default_mark);
                $isCorrect = false;
                $feedback = "Incorrect. None of your answers were correct.";
            }
        } else {
            // Single answer mode - highest grade wins
            $studentMarks = 0;
            $studentFeedback = "Incorrect.";

            foreach ($studentAnswerIds as $answerId) {
                $answer = $answers->firstWhere('id', $answerId);
                if ($answer && $answer->grade_fraction > 0) {
                    $answerMarks = $answer->grade_fraction * $this->question->default_mark;
                    if ($answerMarks > $studentMarks) {
                        $studentMarks = $answerMarks;
                        $studentFeedback = $answer->feedback ?? "Correct!";
                        $isCorrect = $answer->grade_fraction >= 1.0;
                    }
                }
            }

            $marks = $studentMarks;
            $feedback = $studentFeedback;
        }

        return [
            'marks_awarded' => round($marks, 2),
            'is_correct'    => $isCorrect,
            'feedback'      => $feedback,
            'auto_graded'   => true,
        ];
    }

    public function isValidResponse(array $response): bool
    {
        return !empty($response['answer_id']) ||
               (is_array($response['answer_id'] ?? null) && count($response['answer_id']) > 0);
    }

    public function getFeedback(array $response): ?string
    {
        $grading = $this->gradeResponse($response);
        return $grading['feedback'] ?? null;
    }

    private function normalizeChoiceNumbering(string $numbering): string
    {
        // Map common formats to standard values
        $mapping = [
            'a,b,c'     => 'a',
            'A,B,C'     => 'A',
            '1,2,3'     => '1',
            'i,ii,iii'  => 'i',
            'I,II,III'  => 'I',
        ];

        return $mapping[$numbering] ?? $numbering;
    }
}
