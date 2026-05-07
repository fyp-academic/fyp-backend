<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\QuizQuestion;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptResponse;
use Illuminate\Support\Facades\Auth;

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

    /**
     * POST /api/v1/activities/{id}/quiz-attempt
     * Start a new quiz attempt for the authenticated student.
     */
    public function startAttempt(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $activity = Activity::where('id', $id)
            ->where('type', 'quiz')
            ->firstOrFail();

        // Get or create the next attempt number
        $lastAttempt = QuizAttempt::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->orderByDesc('attempt_number')
            ->first();

        $attemptNumber = ($lastAttempt ? $lastAttempt->attempt_number : 0) + 1;

        // Create a new quiz attempt
        $attempt = QuizAttempt::create([
            'id' => Str::uuid()->toString(),
            'activity_id' => $id,
            'student_id' => $user->id,
            'course_id' => $activity->course_id,
            'status' => 'in_progress',
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
        ]);

        return response()->json([
            'message' => 'Quiz attempt started.',
            'data' => $attempt,
        ], 201);
    }

    /**
     * GET /api/v1/quiz-attempts/{id}
     * Get a specific quiz attempt with responses.
     */
    public function getAttempt(string $id): JsonResponse
    {
        $attempt = QuizAttempt::with(['responses.question', 'responses.answer'])
            ->findOrFail($id);

        return response()->json(['data' => $attempt]);
    }

    /**
     * POST /api/v1/quiz-attempts/{id}/submit
     * Submit a quiz attempt with all student responses.
     */
    public function submitAttempt(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        $attempt = QuizAttempt::findOrFail($id);

        // Verify ownership
        if ($attempt->student_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|string|exists:quiz_questions,id',
            'responses.*.answer_id' => 'sometimes|nullable|string|exists:quiz_answers,id',
            'responses.*.response_text' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Store responses
        $totalScore = 0;
        $maxScore = 0;

        foreach ($request->responses as $response) {
            $question = QuizQuestion::findOrFail($response['question_id']);
            $maxScore += $question->default_mark ?? 1;

            $attemptResponse = QuizAttemptResponse::create([
                'id' => Str::uuid()->toString(),
                'attempt_id' => $id,
                'question_id' => $response['question_id'],
                'answer_id' => $response['answer_id'] ?? null,
                'response_text' => $response['response_text'] ?? null,
                'marks_max' => $question->default_mark ?? 1,
            ]);

            // Auto-grade multiple choice questions
            if ($response['answer_id'] ?? null) {
                $answer = QuizAnswer::findOrFail($response['answer_id']);
                $marks = ($answer->grade_fraction ?? 0) * ($question->default_mark ?? 1);
                $attemptResponse->marks_awarded = $marks;
                $attemptResponse->save();
                $totalScore += $marks;
            }
        }

        // Update attempt
        $attempt->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'score' => $totalScore,
            'max_score' => $maxScore,
        ]);

        return response()->json([
            'message' => 'Quiz attempt submitted.',
            'data' => $attempt->load('responses'),
        ]);
    }

    /**
     * GET /api/v1/my-quiz-attempts
     * Get all quiz attempts for the authenticated student.
     */
    public function myAttempts(): JsonResponse
    {
        $user = Auth::user();
        $attempts = QuizAttempt::where('student_id', $user->id)
            ->with('activity', 'course')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $attempts]);
    }
}
