<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackResponse;

class FeedbackController extends Controller
{
    // ── Questions ───────────────────────────────────────────────────────

    /**
     * GET /api/v1/activities/{id}/feedback-questions
     * List all questions in a feedback activity.
     */
    public function questions(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $questions = FeedbackQuestion::where('activity_id', $id)
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $questions, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/feedback-questions
     * Add a question to the feedback form.
     */
    public function storeQuestion(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'          => 'required|string|in:text,textarea,numeric,multichoice,rating,info',
            'question_text' => 'required|string',
            'options'       => 'sometimes|nullable|array',
            'required'      => 'sometimes|boolean',
            'sort_order'    => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $maxOrder = FeedbackQuestion::where('activity_id', $id)->max('sort_order') ?? -1;

        $question = FeedbackQuestion::create([
            'id'            => Str::uuid()->toString(),
            'activity_id'   => $id,
            'type'          => $request->type,
            'question_text' => $request->question_text,
            'options'       => $request->input('options'),
            'required'      => $request->input('required', false),
            'sort_order'    => $request->input('sort_order', $maxOrder + 1),
        ]);

        return response()->json(['message' => 'Question created.', 'data' => $question], 201);
    }

    /**
     * DELETE /api/v1/feedback-questions/{id}
     * Remove a feedback question.
     */
    public function destroyQuestion(string $id): JsonResponse
    {
        $question = FeedbackQuestion::findOrFail($id);
        $question->responses()->delete();
        $question->delete();

        return response()->json(['message' => 'Question deleted.']);
    }

    // ── Responses ──────────────────────────────────────────────────────

    /**
     * GET /api/v1/activities/{id}/feedback-responses
     * List all responses for a feedback activity.
     */
    public function responses(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $responses = FeedbackResponse::where('activity_id', $id)
            ->with(['student', 'question'])
            ->get();

        return response()->json(['data' => $responses, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/feedback-responses
     * Student submits feedback responses (batch).
     */
    public function submitResponses(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'answers'                 => 'required|array|min:1',
            'answers.*.question_id'   => 'required|string|exists:feedback_questions,id',
            'answers.*.response_value'=> 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $user  = $request->user();
        $saved = [];

        foreach ($request->answers as $answer) {
            $saved[] = FeedbackResponse::updateOrCreate(
                [
                    'activity_id' => $id,
                    'question_id' => $answer['question_id'],
                    'student_id'  => $user->id,
                ],
                [
                    'id'             => Str::uuid()->toString(),
                    'response_value' => $answer['response_value'],
                ]
            );
        }

        return response()->json(['message' => 'Feedback submitted.', 'data' => $saved], 201);
    }
}
