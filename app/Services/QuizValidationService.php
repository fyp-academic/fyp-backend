<?php

namespace App\Services;

class QuizValidationService
{
    public static function baseRules(bool $isUpdate = false): array
    {
        $sometimes = $isUpdate ? 'sometimes|' : '';
        return [
            'type' => ($isUpdate ? 'sometimes|' : 'required|') . 'string|in:multiple_choice,true_false,matching,short_answer,numerical,essay,drag_drop',
            'question_text' => $sometimes . 'required|string|min:1',
            'category' => 'sometimes|string|max:255',
            'default_mark' => 'sometimes|numeric|min:0',
            'shuffle_answers' => 'sometimes|boolean',
            'choice_numbering' => 'sometimes|string',
            'penalty' => 'sometimes|numeric|min:0|max:1',
        ];
    }

    public static function rulesForType(string $type, bool $isUpdate = false): array
    {
        $rules = [];
        $sometimes = $isUpdate ? 'sometimes|' : '';

        switch ($type) {
            case 'multiple_choice':
                $rules = [
                    'answers' => $sometimes . 'required|array|min:2',
                    'answers.*.answer_text' => $sometimes . 'required|string',
                    'answers.*.grade_fraction' => $sometimes . 'required|numeric|min:-1|max:1',
                ];
                break;
            case 'true_false':
                $rules = [
                    'correct_answer' => $sometimes . 'required|string|in:true,false,1,0,True,False',
                    'answers' => 'sometimes|array',
                ];
                break;
            case 'matching':
                $rules = [
                    'matching_pairs' => $sometimes . 'required|array|min:1',
                    'matching_pairs.*.left' => $sometimes . 'required|string',
                    'matching_pairs.*.right' => $sometimes . 'required|string',
                ];
                break;
            case 'short_answer':
                $rules = [
                    'correct_answer' => $sometimes . 'required|string',
                ];
                break;
            case 'numerical':
                $rules = [
                    'correct_answer' => $sometimes . 'required|numeric',
                    'tolerance' => $sometimes . 'required|numeric|min:0',
                ];
                break;
            case 'essay':
                $rules = [
                    'max_words' => 'sometimes|integer|min:0',
                    'requires_manual_grading' => 'sometimes|boolean',
                ];
                break;
            case 'drag_drop':
                $rules = [
                    'drop_zones' => $sometimes . 'required|array|min:1',
                    'draggables' => $sometimes . 'required|array|min:1',
                ];
                break;
            default:
                $rules = [];
        }

        return $rules;
    }
}
