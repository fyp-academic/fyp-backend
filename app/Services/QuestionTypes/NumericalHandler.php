<?php

namespace App\Services\QuestionTypes;

/**
 * Numerical Question Handler
 * Supports:
 * - Numeric answers with tolerance
 * - Tolerance types: relative (%), nominal (absolute), geometric (factor)
 * - Optional unit handling
 */
class NumericalHandler extends QuestionTypeHandler
{
    public function getValidationRules(): array
    {
        return [
            'type'              => 'required|in:numerical',
            'question_text'     => 'required|string|min:3',
            'correct_answer'    => 'required|numeric',
            'tolerance_type'    => 'required|in:relative,nominal,geometric',
            'tolerance_value'   => 'required|numeric|min:0',
            'unit_handling'     => 'nullable|in:no_units,optional_units,required_units',
            'units'             => 'nullable|array',
            'default_mark'      => 'numeric|min:0',
        ];
    }

    public function processQuestionData(array $data): array
    {
        // Ensure correct_answer is a valid number
        if (!empty($data['correct_answer'])) {
            $data['correct_answer'] = (string) floatval($data['correct_answer']);
        }

        // Default values
        $data['tolerance_type'] = $data['tolerance_type'] ?? 'relative';
        $data['tolerance_value'] = $data['tolerance_value'] ?? 0.01; // 1% by default
        $data['unit_handling'] = $data['unit_handling'] ?? 'no_units';

        return $data;
    }

    public function gradeResponse(array $response): array
    {
        $studentResponse = $response['response_text'] ?? '';

        if (empty($studentResponse)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'You must provide a numerical answer.',
                'auto_graded'   => true,
            ];
        }

        // Extract number and optional unit
        [$numericAnswer, $unit] = $this->parseNumericResponse($studentResponse);

        if ($numericAnswer === null) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => "\"$studentResponse\" is not a valid numerical answer.",
                'auto_graded'   => true,
            ];
        }

        // Validate unit if required
        if ($this->question->unit_handling === 'required_units' && empty($unit)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => 'Your answer must include a unit.',
                'auto_graded'   => true,
            ];
        }

        // Convert to correct unit if provided
        if (!empty($unit) && !$this->isValidUnit($unit)) {
            return [
                'marks_awarded' => 0,
                'is_correct'    => false,
                'feedback'      => "\"$unit\" is not a valid unit for this question.",
                'auto_graded'   => true,
            ];
        }

        $correctAnswer = floatval($this->question->correct_answer);

        // Apply unit conversion if needed
        if (!empty($unit)) {
            $numericAnswer = $this->convertToBaseUnit($numericAnswer, $unit);
        }

        // Check if answer is within tolerance
        if ($this->isWithinTolerance($numericAnswer, $correctAnswer)) {
            return [
                'marks_awarded' => $this->question->default_mark,
                'is_correct'    => true,
                'feedback'      => "Correct! $studentResponse is within the acceptable tolerance.",
                'auto_graded'   => true,
            ];
        }

        $tolerance = $this->formatTolerance();
        return [
            'marks_awarded' => 0,
            'is_correct'    => false,
            'feedback'      => "Incorrect. Your answer was $studentResponse but the correct answer is $correctAnswer (tolerance: $tolerance).",
            'auto_graded'   => true,
        ];
    }

    public function isValidResponse(array $response): bool
    {
        $text = $response['response_text'] ?? '';
        return !empty(trim($text));
    }

    public function getFeedback(array $response): ?string
    {
        $grading = $this->gradeResponse($response);
        return $grading['feedback'] ?? null;
    }

    /**
     * Parse response into number and optional unit
     * E.g., "9.8 m/s" → [9.8, "m/s"], "42" → [42, null]
     */
    private function parseNumericResponse(string $response): array
    {
        $response = trim($response);

        // Try to match pattern: number [space] unit
        if (preg_match('/^([+-]?\d*\.?\d+(?:[eE][+-]?\d+)?)\s*(.*)$/', $response, $matches)) {
            $number = floatval($matches[1]);
            $unit = trim($matches[2]) ?: null;
            return [$number, $unit];
        }

        return [null, null];
    }

    /**
     * Check if answer is within tolerance
     */
    private function isWithinTolerance(float $answer, float $correct): bool
    {
        $tolerance = $this->question->tolerance_value ?? 0;

        return match ($this->question->tolerance_type) {
            'relative'   => abs($answer - $correct) <= (abs($correct) * $tolerance),
            'nominal'    => abs($answer - $correct) <= $tolerance,
            'geometric'  => ($answer >= $correct / $tolerance) && ($answer <= $correct * $tolerance),
            default      => false,
        };
    }

    /**
     * Check if unit is in allowed units list
     */
    private function isValidUnit(string $unit): bool
    {
        if ($this->question->unit_handling === 'no_units') {
            return false;
        }

        $allowedUnits = $this->question->units ?? [];
        if (empty($allowedUnits)) {
            return true; // Any unit allowed if no restriction
        }

        $unitNames = array_column($allowedUnits, 'unit');
        return in_array($unit, $unitNames);
    }

    /**
     * Convert answer to base unit for comparison
     */
    private function convertToBaseUnit(float $value, string $unit): float
    {
        $allowedUnits = $this->question->units ?? [];

        foreach ($allowedUnits as $u) {
            if ($u['unit'] === $unit) {
                return $value * ($u['multiplier'] ?? 1);
            }
        }

        return $value;
    }

    /**
     * Format tolerance for display
     */
    private function formatTolerance(): string
    {
        $tolerance = $this->question->tolerance_value ?? 0;

        return match ($this->question->tolerance_type) {
            'relative'   => round($tolerance * 100, 2) . '%',
            'nominal'    => '±' . $tolerance,
            'geometric'  => '×' . round($tolerance, 2),
            default      => $tolerance,
        };
    }
}
