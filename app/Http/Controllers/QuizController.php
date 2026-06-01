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
use App\Services\QuestionTypes\QuestionTypeHandlerFactory;
use App\Traits\TimeEnforcementHelper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class QuizController extends Controller
{
    use TimeEnforcementHelper;

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
            'choice_numbering'  => 'sometimes|string|in:none,a,A,i,I,1',
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
            'choice_numbering'  => 'sometimes|string|in:none,a,A,i,I,1',
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

            // Check time restrictions on the quiz
            $settings = $activity->settings ?? [];
            $startTime = isset($settings['start_time']) 
                ? new \DateTime($settings['start_time']) 
                : null;
            $endTime = $activity->due_date ? $activity->due_date->endOfDay() : null;
            
            // Convert to Carbon instances if needed
            if ($startTime && !$startTime instanceof \Carbon\Carbon) {
                $startTime = \Carbon\Carbon::parse($startTime);
            }
            
            // Check time window
            $timeStatus = $this->getActivityTimeStatus($startTime, $endTime);
            
            if (!$timeStatus['can_attempt']) {
                $reason = $timeStatus['reason'] ?? 'unknown';
                $message = $reason === 'not_started' 
                    ? 'Quiz has not started yet. Please try again after the start time.'
                    : 'Quiz submission window has closed. No further attempts allowed.';
                
                Log::warning('Quiz attempt blocked due to time restriction', [
                    'activity_id' => $id,
                    'student_id' => $user->id,
                    'reason' => $reason,
                    'time_status' => $timeStatus,
                ]);
                
                return response()->json([
                    'message' => $message,
                    'error' => 'time_restriction',
                    'reason' => $reason,
                    'time_status' => $timeStatus,
                ], 403);
            }

            // Get or create the next attempt number
            $lastAttempt = QuizAttempt::where('activity_id', $id)
                ->where('student_id', $user->id)
                ->orderByDesc('attempt_number')
                ->first();

            // Check if quiz has been submitted - if so, only allow review
            if ($lastAttempt && in_array($lastAttempt->status, ['submitted', 'graded', 'pending_review'])) {
                Log::info('Student attempting to retake submitted quiz', [
                    'activity_id' => $id,
                    'student_id' => $user->id,
                    'last_attempt_id' => $lastAttempt->id,
                    'last_attempt_status' => $lastAttempt->status,
                ]);
                
                return response()->json([
                    'message' => 'This quiz has already been submitted. You can only review your previous attempt.',
                    'error' => 'quiz_already_submitted',
                    'attempt_id' => $lastAttempt->id,
                ], 422);
            }

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
     * For submitted/graded attempts, includes all questions with correct answers for review.
     */
    public function getAttempt(string $id): JsonResponse
    {
        $attempt = QuizAttempt::with(['responses.question', 'responses.answer'])
            ->findOrFail($id);

        // For submitted or graded attempts, include all questions with correct answers for review
        if (in_array($attempt->status, ['submitted', 'graded', 'pending_review'])) {
            $activity = Activity::findOrFail($attempt->activity_id);
            $allQuestions = QuizQuestion::where('activity_id', $attempt->activity_id)
                ->with('answers')
                ->get();
            
            $attempt->all_questions = $allQuestions;
            $attempt->review_mode = true;
        }

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

        // Store responses and grade using type-specific handlers
        $totalScore = 0;
        $maxScore = 0;
        $needsManualGrading = false;

        foreach ($request->responses as $response) {
            $question = QuizQuestion::findOrFail($response['question_id']);
            $maxScore += $question->default_mark ?? 1;

            try {
                // Get appropriate handler for this question type
                $handler = QuestionTypeHandlerFactory::create($question);

                // Validate response format
                if (!$handler->isValidResponse($response)) {
                    Log::warning("Invalid response for {$question->type} question", [
                        'question_id' => $response['question_id'],
                        'attempt_id' => $id,
                    ]);
                    // Store even invalid responses for review
                    QuizAttemptResponse::create([
                        'id' => Str::uuid()->toString(),
                        'attempt_id' => $id,
                        'question_id' => $response['question_id'],
                        'answer_id' => $response['answer_id'] ?? null,
                        'response_text' => $response['response_text'] ?? null,
                        'response_data' => $response['response_data'] ?? null,
                        'marks_max' => $question->default_mark ?? 1,
                        'marks_awarded' => 0,
                        'is_correct' => false,
                        'auto_graded' => false,
                    ]);
                    continue;
                }

                // Grade the response
                $grading = $handler->gradeResponse($response);

                $attemptResponse = QuizAttemptResponse::create([
                    'id' => Str::uuid()->toString(),
                    'attempt_id' => $id,
                    'question_id' => $response['question_id'],
                    'answer_id' => $response['answer_id'] ?? null,
                    'response_text' => $response['response_text'] ?? null,
                    'response_data' => $response['response_data'] ?? null,
                    'marks_max' => $question->default_mark ?? 1,
                    'marks_awarded' => $grading['marks_awarded'] ?? 0,
                    'feedback' => $grading['feedback'] ?? null,
                    'is_correct' => $grading['is_correct'] ?? null,
                    'auto_graded' => $grading['auto_graded'] ?? false,
                    'graded_at' => now(),
                ]);

                if ($grading['auto_graded'] ?? false) {
                    $totalScore += $grading['marks_awarded'] ?? 0;
                } else {
                    $needsManualGrading = true;
                }

                Log::info("Response graded for {$question->type} question", [
                    'question_id' => $response['question_id'],
                    'marks_awarded' => $grading['marks_awarded'],
                    'auto_graded' => $grading['auto_graded'],
                ]);
            } catch (Exception $e) {
                Log::error("Error grading question {$response['question_id']}", [
                    'error' => $e->getMessage(),
                    'attempt_id' => $id,
                ]);
                // Mark for manual grading on error
                QuizAttemptResponse::create([
                    'id' => Str::uuid()->toString(),
                    'attempt_id' => $id,
                    'question_id' => $response['question_id'],
                    'answer_id' => $response['answer_id'] ?? null,
                    'response_text' => $response['response_text'] ?? null,
                    'marks_max' => $question->default_mark ?? 1,
                    'feedback' => 'Error during grading - requires manual review.',
                    'auto_graded' => false,
                ]);
                $needsManualGrading = true;
            }
        }

        // Update attempt with results
        $attempt->update([
            'status' => $needsManualGrading ? 'pending_review' : 'submitted',
            'submitted_at' => now(),
            'score' => $totalScore,
            'max_score' => $maxScore,
        ]);

        $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 2) : 0;

        $attempt->load('responses');
        $attempt->needs_grading = $needsManualGrading;
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

    /**
     * GET /api/v1/activities/{id}/essay-attempts
     * Get all quiz attempts with essay questions that need manual grading for an activity.
     * Instructor endpoint for grading essays.
     */
    public function essayAttempts(string $activityId): JsonResponse
    {
        try {
            $activity = Activity::findOrFail($activityId);

            // Get all attempts for this activity that have essay responses needing grading
            $attempts = QuizAttempt::where('activity_id', $activityId)
                ->with(['responses.question', 'responses.answer'])
                ->whereIn('status', ['pending_review', 'submitted'])
                ->orderByDesc('submitted_at')
                ->get()
                ->map(function ($attempt) {
                    $essayResponses = $attempt->responses->filter(function ($response) {
                        return $response->question && $response->question->type === 'essay';
                    });

                    if ($essayResponses->isEmpty()) {
                        return null;
                    }

                    return [
                        'attempt_id'      => $attempt->id,
                        'student_id'      => $attempt->student_id,
                        'course_id'       => $attempt->course_id,
                        'activity_id'     => $attempt->activity_id,
                        'activity_name'   => $activity->name,
                        'attempt_number'  => $attempt->attempt_number,
                        'status'          => $attempt->status,
                        'submitted_at'    => $attempt->submitted_at,
                        'score'           => $attempt->score,
                        'max_score'       => $attempt->max_score,
                        'essay_responses' => $essayResponses->map(function ($response) use ($activity) {
                            return [
                                'response_id'     => $response->id,
                                'question_id'     => $response->question_id,
                                'question_text'   => $response->question->question_text ?? '',
                                'question_marks'  => $response->question->default_mark ?? 1,
                                'student_response' => $response->response_text,
                                'marks_awarded'   => $response->marks_awarded,
                                'marks_max'       => $response->marks_max,
                                'feedback'        => $response->feedback,
                                'is_graded'       => $response->marks_awarded !== null,
                            ];
                        })->values()->toArray(),
                    ];
                })
                ->filter(fn($attempt) => $attempt !== null)
                ->values();

            return response()->json(['data' => $attempts, 'activity_id' => $activityId]);
        } catch (\Throwable $e) {
            Log::error('Error retrieving essay attempts', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to retrieve essay attempts.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * PUT /api/v1/quiz-attempt-responses/{id}/grade
     * Grade an essay response
     */
    public function gradeEssayResponse(Request $request, string $responseId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'marks_awarded' => 'required|numeric|min:0',
                'feedback'      => 'sometimes|nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $response = QuizAttemptResponse::findOrFail($responseId);
            $question = QuizQuestion::findOrFail($response->question_id);

            // Only allow grading essays
            if ($question->type !== 'essay') {
                return response()->json(['message' => 'Only essay questions can be graded manually.'], 422);
            }

            // Validate marks don't exceed max
            if ($request->marks_awarded > $response->marks_max) {
                return response()->json([
                    'message' => 'Marks awarded cannot exceed maximum marks.',
                    'error' => "Max: {$response->marks_max}",
                ], 422);
            }

            $response->update([
                'marks_awarded' => $request->marks_awarded,
                'feedback'      => $request->input('feedback'),
                'is_correct'    => $request->marks_awarded == $response->marks_max,
                'auto_graded'   => false,
                'graded_at'     => now(),
            ]);

            // Update attempt score if all responses are now graded
            $attempt = QuizAttempt::findOrFail($response->attempt_id);
            $allResponses = $attempt->responses;
            $totalScore = 0;
            $allGraded = true;

            foreach ($allResponses as $resp) {
                if ($resp->marks_awarded !== null) {
                    $totalScore += $resp->marks_awarded;
                } else {
                    $allGraded = false;
                }
            }

            if ($allGraded) {
                $attempt->update([
                    'status' => 'submitted',
                    'score'  => $totalScore,
                ]);
            }

            Log::info('Essay response graded', [
                'response_id' => $responseId,
                'marks_awarded' => $request->marks_awarded,
                'attempt_id' => $response->attempt_id,
            ]);

            return response()->json([
                'message' => 'Essay response graded successfully.',
                'data' => $response,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error grading essay response', [
                'response_id' => $responseId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to grade essay response.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
