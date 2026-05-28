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
use App\Models\Course;
use App\Models\CognitiveSignal;
use App\Services\NotificationService;
use App\Services\EngagementComputationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QuizController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
        private EngagementComputationService $engagement,
    ) {}

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

        Log::info('Quiz questions fetched', [
            'activity_id' => $id,
            'activity_name' => $activity->name,
            'question_count' => $questions->count(),
            'question_ids' => $questions->pluck('id')->toArray(),
        ]);

        return response()->json(['data' => $questions, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/questions
     * Add a new question to a quiz activity's question bank.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $allowedTypes = 'multiple_choice,true_false,matching,short_answer,numerical,essay,calculated,drag_drop,drag_drop_text,drag_drop_markers,calculated_multichoice,calculated_simple';
        $validator = Validator::make($request->all(), [
            'type'              => 'required|string|in:' . $allowedTypes,
            'question_text'     => 'required|string|min:1',
            'category'          => 'sometimes|string|max:255',
            'default_mark'      => 'sometimes|numeric|min:0',
            'shuffle_answers'   => 'sometimes|boolean',
            'choice_numbering'  => 'sometimes|string|in:none,a,b,c,A,B,C,i,ii,iii,I,II,III,1,2,3,a,b,c...,A,B,C...,i,ii,iii...,I,II,III...,1,2,3...',
            'penalty'           => 'sometimes|numeric|min:0|max:1',
            'matching_pairs'    => 'sometimes|nullable|array',
            'correct_answer'    => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Quiz question creation validation failed', [
                'activity_id' => $id,
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all(),
            ]);
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activity = Activity::findOrFail($id);

        $question = QuizQuestion::create([
            'id'               => Str::uuid()->toString(),
            'activity_id'      => $id,
            'course_id'        => $activity->course_id,
            'type'             => $request->type,
            'question_text'    => $request->question_text,
            'category'         => $request->input('category', ''),
            'default_mark'     => $request->input('default_mark', 1),
            'shuffle_answers'  => $request->input('shuffle_answers', true),
            'choice_numbering' => $request->input('choice_numbering', 'none'),
            'penalty'          => $request->input('penalty', 0),
            'matching_pairs'   => $request->input('matching_pairs'),
            'correct_answer'   => $request->input('correct_answer'),
        ]);

        // Auto-generate True/False answers if not provided
        if ($question->type === 'true_false' && $request->has('answers')) {
            foreach ($request->answers as $a) {
                QuizAnswer::create([
                    'id'             => Str::uuid()->toString(),
                    'question_id'    => $question->id,
                    'text'           => $a['answer_text'] ?? $a['text'] ?? '',
                    'grade_fraction' => $a['grade_fraction'] ?? $a['grade'] ?? 0,
                    'feedback'       => $a['feedback'] ?? '',
                    'sort_order'     => $a['sort_order'] ?? 0,
                ]);
            }
        }

        Log::info('Quiz question created', [
            'activity_id' => $id,
            'question_id' => $question->id,
            'type' => $question->type,
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
        $allowedTypes = 'multiple_choice,true_false,matching,short_answer,numerical,essay,calculated,drag_drop,drag_drop_text,drag_drop_markers,calculated_multichoice,calculated_simple';

        $validator = Validator::make($request->all(), [
            'type'              => 'sometimes|string|in:' . $allowedTypes,
            'question_text'     => 'sometimes|string|min:1',
            'category'          => 'sometimes|string|max:255',
            'default_mark'      => 'sometimes|numeric|min:0',
            'shuffle_answers'   => 'sometimes|boolean',
            'choice_numbering'  => 'sometimes|string|in:none,a,b,c,A,B,C,i,ii,iii,I,II,III,1,2,3,a,b,c...,A,B,C...,i,ii,iii...,I,II,III...,1,2,3...',
            'penalty'           => 'sometimes|numeric|min:0|max:1',
            'matching_pairs'    => 'sometimes|nullable|array',
            'correct_answer'    => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $question->update($request->only([
            'type', 'question_text', 'category', 'default_mark', 'shuffle_answers', 'choice_numbering', 'penalty', 'matching_pairs', 'correct_answer',
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
        try {
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

            try {
                $this->engagement->logEvent(
                    userId:       $user->id,
                    eventType:    'quiz_start',
                    courseId:     $activity->course_id,
                    resourceType: 'activity',
                    resourceId:   $id,
                    metadata:     ['attempt_id' => $attempt->id, 'attempt_number' => $attemptNumber],
                    loginSessionId: $request->input('login_session_id'),
                );
            } catch (\Throwable $engEx) {
                Log::warning('Engagement: failed to log quiz_start', ['activity' => $id, 'error' => $engEx->getMessage()]);
            }

            return response()->json([
                'message' => 'Quiz attempt started.',
                'data' => $attempt,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to start quiz attempt.',
                'error' => $e->getMessage(),
            ], 500);
        }
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

        // Prevent duplicate submission
        if ($attempt->status === 'submitted') {
            return response()->json([
                'message' => 'This quiz attempt has already been submitted.',
                'data' => $attempt,
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|string|exists:quiz_questions,id',
            'responses.*.answer_id' => 'sometimes|nullable|string|exists:quiz_answers,id',
            'responses.*.response_text' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Quiz submit validation failed', [
                'attempt_id' => $id,
                'student_id' => $user->id,
                'errors' => $validator->errors()->toArray(),
                'input' => $request->all(),
            ]);
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Store responses
        $totalScore = 0;
        $maxScore = 0;
        $needsGrading = false;

        foreach ($request->responses as $response) {
            $question = QuizQuestion::findOrFail($response['question_id']);
            $maxScore += $question->default_mark ?? 1;

            $hasAnswer = !empty($response['answer_id'] ?? null);
            if (!$hasAnswer && !empty($response['response_text'] ?? null)) {
                $needsGrading = true;
            }

            $attemptResponse = QuizAttemptResponse::create([
                'id' => Str::uuid()->toString(),
                'attempt_id' => $id,
                'question_id' => $response['question_id'],
                'answer_id' => $response['answer_id'] ?? null,
                'response_text' => $response['response_text'] ?? null,
                'marks_max' => $question->default_mark ?? 1,
            ]);

            // Auto-grade multiple choice questions
            if ($hasAnswer) {
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

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        $attempt->load('responses');
        $attempt->needs_grading = $needsGrading;
        $attempt->score_percentage = $percentage;

        // Engagement: log quiz_submit event + update skip_rate in cognitive signals
        try {
            $totalQuestions  = QuizQuestion::where('activity_id', $attempt->activity_id)->count();
            $answeredCount   = count($request->responses);
            $skippedCount    = max(0, $totalQuestions - $answeredCount);
            $skipRate        = $totalQuestions > 0 ? round(($skippedCount / $totalQuestions) * 100, 2) : 0;

            $this->engagement->logEvent(
                userId:       $user->id,
                eventType:    'quiz_submit',
                courseId:     $attempt->course_id,
                resourceType: 'activity',
                resourceId:   $attempt->activity_id,
                value:        $percentage,
                metadata:     [
                    'attempt_id'    => $attempt->id,
                    'score'         => $totalScore,
                    'max_score'     => $maxScore,
                    'skip_rate'     => $skipRate,
                    'needs_grading' => $needsGrading,
                ],
                loginSessionId: $request->input('login_session_id'),
            );

            CognitiveSignal::where('learner_id', $user->id)
                ->where('course_id', $attempt->course_id)
                ->orderBy('week_number', 'desc')
                ->limit(1)
                ->update(['quiz_question_skip_rate' => $skipRate]);
        } catch (\Throwable $e) {
            Log::warning('Engagement: failed to log quiz_submit', ['attempt' => $attempt->id, 'error' => $e->getMessage()]);
        }

        // Notify instructor of quiz submission
        try {
            $activity = Activity::findOrFail($attempt->activity_id);
            $course = Course::findOrFail($activity->course_id);
            $instructorId = $course->instructor_id;

            $this->notificationService->sendToUser(
                $instructorId,
                'new_submission',
                'in_app',
                'New Quiz Submission',
                "A student has submitted a quiz attempt for '{$activity->name}'.",
                [
                    'activity_id' => $activity->id,
                    'activity_type' => 'quiz',
                    'attempt_id' => $attempt->id,
                    'course_id' => $course->id,
                    'needs_grading' => $needsGrading,
                ],
                $attempt->id
            );
        } catch (\Exception $e) {
            Log::warning('Failed to send instructor notification for quiz submission', [
                'attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Quiz attempt submitted.',
            'data' => $attempt,
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
