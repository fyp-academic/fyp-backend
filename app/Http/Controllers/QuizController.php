<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\QuizQuestion;
use App\Models\QuizAnswer;

class QuizController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/questions
     * Return all questions in the question bank for a quiz activity.
     */
    public function index(string $id): JsonResponse
    {
        $activity  = Activity::findOrFail($id);
        $questions = QuizQuestion::where('activity_id', $id)
            ->with('answers')
            ->get();

        return response()->json(['data' => $questions, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/questions
     * Add a new question to a quiz activity's question bank.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'            => 'required|string|in:multiple_choice,true_false,matching,short_answer,numerical,essay,calculated,drag_drop',
            'question_text'   => 'required|string',
            'category'        => 'sometimes|string|max:255',
            'default_mark'    => 'sometimes|numeric|min:0',
            'shuffle_answers' => 'sometimes|boolean',
            'penalty'         => 'sometimes|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::findOrFail($id);

        $question = QuizQuestion::create([
            'id'              => Str::uuid()->toString(),
            'activity_id'     => $id,
            'course_id'       => $activity->course_id,
            'type'            => $request->type,
            'question_text'   => $request->question_text,
            'category'        => $request->input('category', ''),
            'default_mark'    => $request->input('default_mark', 1),
            'shuffle_answers' => $request->input('shuffle_answers', true),
            'penalty'         => $request->input('penalty', 0),
        ]);

        return response()->json(['message' => 'Question created.', 'data' => $question], 201);
    }

    /**
     * PUT /api/v1/questions/{id}
     * Edit question text, type, marks, shuffle settings, or penalty.
     */
    public function updateQuestion(Request $request, string $id): JsonResponse
    {
        $question = QuizQuestion::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type'            => 'sometimes|string|in:multiple_choice,true_false,matching,short_answer,numerical,essay,calculated,drag_drop',
            'question_text'   => 'sometimes|string',
            'category'        => 'sometimes|string|max:255',
            'default_mark'    => 'sometimes|numeric|min:0',
            'shuffle_answers' => 'sometimes|boolean',
            'penalty'         => 'sometimes|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question->update($request->only([
            'type', 'question_text', 'category', 'default_mark', 'shuffle_answers', 'penalty',
        ]));

        return response()->json(['message' => 'Question updated.', 'data' => $question]);
    }

    /**
     * DELETE /api/v1/questions/{id}
     * Remove a question and all its associated answer options.
     */
    public function destroyQuestion(string $id): JsonResponse
    {
        $question = QuizQuestion::findOrFail($id);
        $question->answers()->delete();
        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }

    /**
     * GET /api/v1/questions/{id}/answers
     * Return all answer options for a question with grade fractions.
     */
    public function answers(string $id): JsonResponse
    {
        $question = QuizQuestion::findOrFail($id);

        $answers = QuizAnswer::where('question_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $answers, 'question_id' => $id]);
    }

    /**
     * POST /api/v1/questions/{id}/answers
     * Add an answer option with grade fraction (1.0 = correct) and optional feedback.
     */
    public function storeAnswer(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'answer_text'    => 'required|string',
            'grade_fraction' => 'required|numeric|min:-1|max:1',
            'feedback'       => 'sometimes|nullable|string',
            'sort_order'     => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question = QuizQuestion::findOrFail($id);
        $maxOrder = QuizAnswer::where('question_id', $id)->max('sort_order') ?? -1;

        $answer = QuizAnswer::create([
            'id'             => Str::uuid()->toString(),
            'question_id'    => $id,
            'text'           => $request->answer_text,
            'grade_fraction' => $request->grade_fraction,
            'feedback'       => $request->input('feedback'),
            'sort_order'     => $request->input('sort_order', $maxOrder + 1),
        ]);

        return response()->json(['message' => 'Answer created.', 'data' => $answer], 201);
    }
}
