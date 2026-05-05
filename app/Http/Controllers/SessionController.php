<?php

namespace App\Http\Controllers;

use App\Models\Session;
use App\Models\SessionRecording;
use App\Models\User;
use App\Services\Jitsi\AIService;
use App\Services\Jitsi\JitsiTokenService;
use App\Services\Jitsi\RecordingService;
use App\Services\Jitsi\SessionService;
use App\Services\Jitsi\RateLimiterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SessionController extends Controller
{
    private SessionService $sessionService;
    private RecordingService $recordingService;
    private AIService $aiService;
    private JitsiTokenService $tokenService;
    private RateLimiterService $rateLimiterService;

    public function __construct(
        SessionService $sessionService,
        RecordingService $recordingService,
        AIService $aiService,
        JitsiTokenService $tokenService,
        RateLimiterService $rateLimiterService
    ) {
        $this->sessionService = $sessionService;
        $this->recordingService = $recordingService;
        $this->aiService = $aiService;
        $this->tokenService = $tokenService;
        $this->rateLimiterService = $rateLimiterService;
    }

    /**
     * POST /api/sessions
     * Create a new video session
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isInstructor() && !$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized. Only instructors can create sessions.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'course_id' => 'required|string|exists:courses,id',
            'scheduled_at' => 'required|date|after:now',
            'max_participants' => 'nullable|integer|min:2|max:500',
            'password' => 'nullable|string|max:50',
            'recording_enabled' => 'nullable|boolean',
            'chat_enabled' => 'nullable|boolean',
            'raise_hand_enabled' => 'nullable|boolean',
            'waiting_room' => 'nullable|boolean',
            'screen_share_allowed' => 'nullable|boolean',
            'start_muted' => 'nullable|boolean',
            'start_video_off' => 'nullable|boolean',
            'ai_transcription' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $session = $this->sessionService->createSession($user->id, $request->all());
            $session->load('course', 'instructor');

            return response()->json([
                'id' => $session->id,
                'title' => $session->title,
                'course_id' => $session->course_id,
                'course' => [
                    'id' => $session->course->id,
                    'name' => $session->course->name,
                    'title' => $session->course->name,
                ],
                'instructor' => [
                    'id' => $session->instructor->id,
                    'name' => $session->instructor->name,
                ],
                'room_id' => $session->room_id,
                'status' => $session->status,
                'scheduled_at' => $session->scheduled_at,
                'duration' => $session->duration,
                'max_participants' => $session->max_participants,
                'recording_enabled' => $session->recording_enabled,
                'jitsi_domain' => $this->tokenService->getDomain(),
                'created_at' => $session->created_at,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create session: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/sessions
     * List sessions (role-filtered)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Session::query();

        if ($user->isAdmin()) {
            // Admins see all sessions
            $query->with(['course', 'instructor']);
        } elseif ($user->isInstructor()) {
            // Instructors see their own sessions
            $query->where('instructor_id', $user->id)
                ->orWhereHas('course', function ($q) use ($user) {
                    $q->whereHas('degreeProgrammes', function ($q2) use ($user) {
                        $q2->whereHas('instructors', function ($q3) use ($user) {
                            $q3->where('instructor_id', $user->id);
                        });
                    });
                })
                ->with(['course', 'participants']);
        } else {
            // Students see sessions for courses they're enrolled in
            $enrolledCourseIds = $user->enrollments()
                ->where('status', 'active')
                ->pluck('course_id');

            $query->whereIn('course_id', $enrolledCourseIds)
                ->with('course');
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by course
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $sessions = $query->orderBy('scheduled_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($sessions);
    }

    /**
     * GET /api/sessions/:id
     * Get session details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::with(['course', 'instructor', 'participants.user'])->findOrFail($id);

        // Authorization check
        $this->authorizeSessionAccess($user, $session);

        $response = [
            'id' => $session->id,
            'title' => $session->title,
            'course' => [
                'id' => $session->course->id,
                'title' => $session->course->name ?? $session->course->title,
            ],
            'instructor' => [
                'id' => $session->instructor->id,
                'name' => $session->instructor->name,
            ],
            'room_id' => $session->room_id,
            'status' => $session->status,
            'scheduled_at' => $session->scheduled_at,
            'started_at' => $session->started_at,
            'ended_at' => $session->ended_at,
            'duration' => $session->duration,
            'max_participants' => $session->max_participants,
            'recording_enabled' => $session->recording_enabled,
            'chat_enabled' => $session->chat_enabled,
            'raise_hand_enabled' => $session->raise_hand_enabled,
            'waiting_room' => $session->waiting_room,
            'screen_share_allowed' => $session->screen_share_allowed,
            'start_muted' => $session->start_muted,
            'start_video_off' => $session->start_video_off,
            'ai_transcription' => $session->ai_transcription,
            'recording_url' => $session->recording_url,
            'ai_summary' => $session->ai_summary,
            'jitsi_domain' => $this->tokenService->getDomain(),
            'participant_count' => $session->participants->whereNull('left_at')->count(),
            'is_instructor' => $session->instructor_id === $user->id,
        ];

        // Include participants for instructor/admin
        if ($session->instructor_id === $user->id || $user->isAdmin()) {
            $response['participants'] = $session->participants->map(function ($p) {
                return [
                    'id' => $p->user_id,
                    'name' => $p->user->name,
                    'joined_at' => $p->joined_at,
                    'left_at' => $p->left_at,
                    'mic_active' => $p->mic_active,
                    'camera_active' => $p->camera_active,
                    'hands_raised' => $p->hands_raised,
                    'chat_messages' => $p->chat_messages,
                    'attendance_score' => $p->attendance_score,
                ];
            });
        }

        return response()->json($response);
    }

    /**
     * POST /api/sessions/:id/token
     * Generate JWT token for joining session
     */
    public function getToken(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        try {
            $tokenData = $this->sessionService->getSessionToken($id, $user->id);

            return response()->json([
                'token' => $tokenData['token'],
                'room_name' => $tokenData['roomName'],
                'is_moderator' => $tokenData['isModerator'],
                'jitsi_domain' => $this->tokenService->getDomain(),
                'config' => $tokenData['config'],
                'user_info' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->profile_image,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Token generation failed for session {$id}, user {$user->id}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * PATCH /api/sessions/:id/start
     * Start a session
     */
    public function start(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        if ($session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Only the instructor can start this session'], 403);
        }

        try {
            $session = $this->sessionService->startSession($id, $user->id);

            return response()->json([
                'id' => $session->id,
                'status' => $session->status,
                'started_at' => $session->started_at,
                'room_id' => $session->room_id,
                'jitsi_domain' => $this->tokenService->getDomain(),
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * PATCH /api/sessions/:id/end
     * End a session
     */
    public function end(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        if ($session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Only the instructor can end this session'], 403);
        }

        try {
            $session = $this->sessionService->endSession($id, $user->id);

            // Trigger AI summarization if transcription was enabled
            if ($session->ai_transcription && $session->transcripts()->count() > 0) {
                dispatch(function () use ($id, $session) {
                    try {
                        $this->aiService->summariseSession($id);
                    } catch (\Exception $e) {
                        Log::error("Failed to summarize session {$id}: " . $e->getMessage());
                    }
                })->afterResponse();
            }

            return response()->json([
                'id' => $session->id,
                'status' => $session->status,
                'ended_at' => $session->ended_at,
                'duration' => $session->duration,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/sessions/:id/recording/start
     * Start Jibri recording
     */
    public function startRecording(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        if (!$session->recording_enabled) {
            return response()->json(['error' => 'Recording is not enabled for this session'], 400);
        }

        if ($session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Only the instructor can start recording'], 403);
        }

        try {
            $recording = $this->recordingService->startRecording($id, $user->id);

            return response()->json([
                'recording_id' => $recording->id,
                'status' => $recording->status,
                'started_at' => $recording->created_at,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to start recording for session {$id}: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sessions/:id/recording/stop
     * Stop Jibri recording
     */
    public function stopRecording(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        if ($session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Only the instructor can stop recording'], 403);
        }

        try {
            $this->recordingService->stopRecording($id, $user->id);

            return response()->json([
                'message' => 'Recording stop signal sent',
                'status' => 'stopping',
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/sessions/:id/participants
     * Get live participant list
     */
    public function getParticipants(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::with('participants.user')->findOrFail($id);

        if ($session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Only the instructor can view participant details'], 403);
        }

        $participants = $session->participants->map(function ($p) {
            return [
                'id' => $p->user_id,
                'name' => $p->user->name,
                'email' => $p->user->email,
                'avatar' => $p->user->profile_image,
                'joined_at' => $p->joined_at,
                'left_at' => $p->left_at,
                'mic_active' => $p->mic_active,
                'camera_active' => $p->camera_active,
                'hands_raised' => $p->hands_raised,
                'chat_messages' => $p->chat_messages,
                'attendance_score' => $p->attendance_score,
                'is_active' => is_null($p->left_at),
            ];
        });

        return response()->json([
            'session_id' => $id,
            'total_count' => $participants->count(),
            'active_count' => $participants->where('is_active', true)->count(),
            'participants' => $participants,
        ]);
    }

    /**
     * POST /api/sessions/:id/kick/:userId
     * Remove a participant (handled via Jitsi external API on frontend)
     * This endpoint logs the action
     */
    public function kickParticipant(Request $request, string $id, string $userId): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        if ($session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Only the instructor can remove participants'], 403);
        }

        // Mark participant as removed
        $participant = $session->participants()->where('user_id', $userId)->first();
        if ($participant) {
            $participant->update(['left_at' => now()]);
        }

        Log::info("User {$userId} was kicked from session {$id} by instructor {$user->id}");

        return response()->json([
            'message' => 'Participant removed',
            'user_id' => $userId,
            'session_id' => $id,
        ]);
    }

    /**
     * POST /api/sessions/:id/mute-all
     * Mute all participants (handled via Jitsi external API on frontend)
     * This endpoint logs the action
     */
    public function muteAll(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        if ($session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Only the instructor can mute all'], 403);
        }

        Log::info("Mute all triggered for session {$id} by instructor {$user->id}");

        return response()->json([
            'message' => 'Mute all signal sent',
            'session_id' => $id,
        ]);
    }

    /**
     * GET /api/sessions/:id/transcript
     * Get session transcripts
     */
    public function getTranscript(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::with('transcripts.user')->findOrFail($id);

        $this->authorizeSessionAccess($user, $session);

        // Only allow if user participated or is instructor/admin
        $isParticipant = $session->participants()->where('user_id', $user->id)->exists();
        $isInstructor = $session->instructor_id === $user->id;

        if (!$isParticipant && !$isInstructor && !$user->isAdmin()) {
            return response()->json(['error' => 'You must have participated in this session to view transcripts'], 403);
        }

        $transcripts = $session->transcripts->map(function ($t) {
            return [
                'id' => $t->id,
                'speaker' => $t->speaker_name,
                'text' => $t->text,
                'segments' => $t->segments,
                'timestamp' => $t->timestamp,
            ];
        });

        return response()->json([
            'session_id' => $id,
            'transcript_count' => $transcripts->count(),
            'full_text' => $session->getFullTranscriptText(),
            'transcripts' => $transcripts,
        ]);
    }

    /**
     * GET /api/sessions/:id/summary
     * Get AI summary of the session
     */
    public function getSummary(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        $this->authorizeSessionAccess($user, $session);

        if (!$session->ai_summary) {
            return response()->json([
                'session_id' => $id,
                'summary' => null,
                'status' => 'pending',
                'message' => 'Summary not yet generated',
            ]);
        }

        return response()->json([
            'session_id' => $id,
            'summary' => $session->ai_summary,
            'status' => 'completed',
            'generated_at' => $session->updated_at,
        ]);
    }

    /**
     * GET /api/recordings/:id/url
     * Get signed download URL for recording
     */
    public function getRecordingUrl(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $recording = SessionRecording::with('session.course')->findOrFail($id);

        $session = $recording->session;

        // Authorization check
        $this->authorizeSessionAccess($user, $session);

        // Students can only access if recording is completed
        if ($user->isStudent() && $recording->status !== 'completed') {
            return response()->json(['error' => 'Recording not yet available'], 403);
        }

        if (!$recording->isCompleted()) {
            return response()->json([
                'recording_id' => $id,
                'status' => $recording->status,
                'url' => null,
            ]);
        }

        return response()->json([
            'recording_id' => $id,
            'status' => $recording->status,
            'url' => $recording->getDownloadUrl(),
            'expires_at' => now()->addMinutes(60)->toIso8601String(),
            'duration' => $recording->duration,
            'size' => $recording->size,
        ]);
    }

    /**
     * POST /api/webhooks/jibri
     * Webhook receiver for Jibri recording events
     */
    public function handleJibriWebhook(Request $request): JsonResponse
    {
        // Verify webhook signature if configured
        $secret = config('services.jitsi.webhook_secret');
        if ($secret && $request->header('X-Jibri-Signature') !== $secret) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $data = $request->all();

        Log::info('Jibri webhook received', $data);

        try {
            $recording = $this->recordingService->handleRecordingWebhook($data);

            return response()->json([
                'status' => 'success',
                'recording_id' => $recording->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Jibri webhook processing failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/transcribe
     * Upload audio chunk and transcribe
     * Rate limit: 1 request per 10 seconds per user
     */
    public function transcribe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'audio' => 'required|file|mimes:webm,mp3,wav|max:10240', // 10MB max
            'session_id' => 'required|string|exists:video_sessions,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Rate limit check - 1 request per 10 seconds per user
        if (!$this->rateLimiterService->canTranscribe($user->id)) {
            $retryAfter = $this->rateLimiterService->getTranscribeRetryAfter($user->id);
            return response()->json([
                'error' => 'Rate limit exceeded. Maximum 1 transcription per 10 seconds.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        $session = Session::findOrFail($request->session_id);

        if (!$session->ai_transcription) {
            return response()->json(['error' => 'Transcription not enabled for this session'], 400);
        }

        // Verify user is participant
        $isParticipant = $session->participants()->where('user_id', $user->id)->exists();
        if (!$isParticipant && $session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'You are not a participant in this session'], 403);
        }

        // Check for audio capture consent (must be explicitly granted)
        $consentKey = "transcription_consent:{$user->id}:{$request->session_id}";
        if (!cache()->get($consentKey)) {
            return response()->json([
                'error' => 'Audio capture consent required',
                'consent_required' => true,
            ], 403);
        }

        try {
            $file = $request->file('audio');
            $path = $file->store('temp/transcriptions');
            $fullPath = storage_path('app/' . $path);

            $transcript = $this->aiService->transcribeChunk($fullPath, $request->session_id, $user->id);

            // Clean up temp file
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            return response()->json([
                'transcript_id' => $transcript->id,
                'text' => $transcript->text,
                'speaker' => $transcript->speaker_name,
                'timestamp' => $transcript->timestamp,
            ]);

        } catch (\Exception $e) {
            Log::error("Transcription failed: " . $e->getMessage());
            return response()->json(['error' => 'Transcription failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sessions/:id/transcription-consent
     * Grant explicit consent for audio transcription
     */
    public function grantTranscriptionConsent(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        // Verify user is participant
        $isParticipant = $session->participants()->where('user_id', $user->id)->exists();
        if (!$isParticipant && $session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'You are not a participant in this session'], 403);
        }

        // Store consent in cache for session duration
        $consentKey = "transcription_consent:{$user->id}:{$id}";
        cache()->put($consentKey, true, now()->addHours(8));

        Log::info("User {$user->id} granted transcription consent for session {$id}");

        return response()->json([
            'message' => 'Consent granted',
            'expires_at' => now()->addHours(8)->toIso8601String(),
        ]);
    }

    /**
     * POST /api/sessions/:id/ask-ai
     * Ask the AI assistant a question
     */
    public function askAI(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->authorizeSessionAccess($user, $session);

        if (!$session->ai_transcription) {
            return response()->json(['error' => 'AI assistant not enabled for this session'], 400);
        }

        try {
            $answer = $this->aiService->answerQuestion($id, $request->question);

            return response()->json([
                'question' => $request->question,
                'answer' => $answer,
                'session_id' => $id,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get answer: ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sessions/:id/participant-left
     * Mark participant as left (called from frontend when user leaves)
     */
    public function participantLeft(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        try {
            $this->sessionService->participantLeft($id, $user->id);

            return response()->json(['message' => 'Participant marked as left']);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/sessions/:id/update-metrics
     * Update participant engagement metrics
     */
    public function updateMetrics(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'mic_active' => 'nullable|boolean',
            'camera_active' => 'nullable|boolean',
            'hands_raised' => 'nullable|integer|min:0',
            'chat_messages' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $metrics = array_filter($request->only([
            'mic_active',
            'camera_active',
            'hands_raised',
            'chat_messages',
        ]), fn ($v) => $v !== null);

        try {
            $this->sessionService->updateParticipantMetrics($id, $user->id, $metrics);

            return response()->json(['message' => 'Metrics updated']);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/sessions/:id/polls
     * Create a new poll (instructor only)
     */
    public function createPoll(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:200',
            'is_multiple_choice' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Rate limit poll creation
        if (!$this->rateLimiterService->canCreatePoll($user->id, $id)) {
            return response()->json([
                'error' => 'Rate limit exceeded. Maximum 10 polls per hour per session.',
            ], 429);
        }

        try {
            $pollService = new \App\Services\Jitsi\PollService();
            $poll = $pollService->createPoll($id, $user->id, $request->all());

            return response()->json([
                'poll_id' => $poll->id,
                'question' => $poll->question,
                'options' => $poll->options,
                'is_multiple_choice' => $poll->is_multiple_choice,
                'is_active' => $poll->is_active,
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/polls/:pollId/vote
     * Vote on a poll
     */
    public function voteOnPoll(Request $request, string $pollId): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'option_index' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $pollService = new \App\Services\Jitsi\PollService();
            $vote = $pollService->vote($pollId, $user->id, $request->option_index);

            return response()->json([
                'vote_id' => $vote->id,
                'option_index' => $vote->option_index,
                'poll_id' => $pollId,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/polls/:pollId/results
     * Get poll results
     */
    public function getPollResults(string $pollId): JsonResponse
    {
        try {
            $pollService = new \App\Services\Jitsi\PollService();
            $results = $pollService->getResults($pollId);

            return response()->json($results);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /api/polls/:pollId/end
     * End a poll (instructor only)
     */
    public function endPoll(Request $request, string $pollId): JsonResponse
    {
        $user = $request->user();

        try {
            $pollService = new \App\Services\Jitsi\PollService();
            $poll = $pollService->endPoll($pollId, $user->id);

            return response()->json([
                'poll_id' => $poll->id,
                'is_active' => $poll->is_active,
                'ended_at' => $poll->ended_at,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    /**
     * GET /api/sessions/:id/polls
     * Get active polls for a session
     */
    public function getSessionPolls(string $id): JsonResponse
    {
        try {
            $pollService = new \App\Services\Jitsi\PollService();
            $polls = $pollService->getActivePolls($id);

            return response()->json(['polls' => $polls]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * POST /api/sessions/:id/generate-quiz
     * Generate quiz from transcript (instructor only)
     */
    public function generateQuiz(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $session = Session::findOrFail($id);

        if ($session->instructor_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Use AI service to generate quiz questions
            $quizData = $this->aiService->generateQuizFromTranscript($id);

            // Notify enrolled students
            foreach ($session->course->enrollments()->where('status', 'active')->get() as $enrollment) {
                $enrollment->user->notifications()->create([
                    'type' => 'quiz_available',
                    'title' => 'New Quiz Available',
                    'body' => "A quiz for '{$session->title}' has been generated.",
                    'payload' => [
                        'session_id' => $id,
                        'course_id' => $session->course_id,
                        'quiz_id' => $quizData['quiz_id'],
                    ],
                ]);
            }

            return response()->json($quizData);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Authorization helper
     */
    private function authorizeSessionAccess(User $user, Session $session): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($session->instructor_id === $user->id) {
            return;
        }

        $isEnrolled = $user->enrollments()
            ->where('course_id', $session->course_id)
            ->where('status', 'active')
            ->exists();

        if (!$isEnrolled) {
            abort(403, 'You are not enrolled in this course');
        }
    }
}
