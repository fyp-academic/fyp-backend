<?php

namespace App\Services\Jitsi;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Session;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SessionService
{
    private JitsiTokenService $tokenService;
    private SocketBroadcaster $broadcaster;

    public function __construct(JitsiTokenService $tokenService)
    {
        $this->tokenService = $tokenService;
        $this->broadcaster = new SocketBroadcaster();
    }

    /**
     * Create a new video session
     */
    public function createSession(string $instructorId, array $data): Session
    {
        $courseId = $data['course_id'];
        $course = Course::findOrFail($courseId);

        // Verify instructor owns this course or is assigned to its programme
        if ($course->instructor_id !== $instructorId) {
            $instructor = User::findOrFail($instructorId);
            $assignedProgrammes = $instructor->assignedDegreeProgrammes->pluck('id')->toArray();
            $courseProgrammes = $course->degreeProgrammes->pluck('id')->toArray();

            if (empty(array_intersect($assignedProgrammes, $courseProgrammes))) {
                throw new \Exception('Instructor not authorized for this course');
            }
        }

        // Generate unique room ID: lms-c{courseId}-s{sessionId}-{random}
        $session = new Session([
            'title' => $data['title'],
            'course_id' => $courseId,
            'instructor_id' => $instructorId,
            'room_id' => '', // temporary, will update after creation
            'status' => 'scheduled',
            'scheduled_at' => $data['scheduled_at'],
            'duration' => $data['duration'] ?? 60,
            'max_participants' => $data['max_participants'] ?? 100,
            'password' => $data['password'] ?? null,
            'recording_enabled' => $data['recording_enabled'] ?? false,
            'chat_enabled' => $data['chat_enabled'] ?? true,
            'raise_hand_enabled' => $data['raise_hand_enabled'] ?? true,
            'waiting_room' => $data['waiting_room'] ?? false,
            'screen_share_allowed' => $data['screen_share_allowed'] ?? true,
            'start_muted' => $data['start_muted'] ?? false,
            'start_video_off' => $data['start_video_off'] ?? false,
            'ai_transcription' => $data['ai_transcription'] ?? true,
        ]);

        $session->save();

        // Generate room ID with session ID
        $randomPart = strtolower(Str::random(8));
        $session->room_id = "lms-c{$courseId}-s{$session->id}-{$randomPart}";
        $session->save();

        Log::info("Created session {$session->id} with room {$session->room_id} for course {$courseId}");

        return $session;
    }

    /**
     * Start a session - mark as live
     */
    public function startSession(string $sessionId, string $instructorId): Session
    {
        $session = Session::findOrFail($sessionId);

        if ($session->instructor_id !== $instructorId) {
            throw new \Exception('Only the session instructor can start the session');
        }

        if ($session->isLive()) {
            return $session->fresh();
        }

        if (!$session->isScheduled()) {
            throw new \Exception('Session cannot be started - current status: ' . $session->status);
        }

        $session->update([
            'status' => 'live',
            'started_at' => now(),
        ]);

        Log::info("Session {$sessionId} started by instructor {$instructorId}");

        // Broadcast session started event
        $this->broadcaster->broadcastSessionStarted($sessionId);

        return $session->fresh();
    }

    /**
     * End a session - mark as ended and calculate duration
     */
    public function endSession(string $sessionId, string $userId): Session
    {
        $session = Session::findOrFail($sessionId);

        // Only instructor or admin can end
        if ($session->instructor_id !== $userId) {
            $user = User::findOrFail($userId);
            if (!$user->isAdmin()) {
                throw new \Exception('Only the session instructor or admin can end the session');
            }
        }

        if (!$session->isLive()) {
            throw new \Exception('Session is not live - cannot end');
        }

        $endedAt = now();
        $duration = $session->started_at ? $session->started_at->diffInMinutes($endedAt) : 0;

        $session->update([
            'status' => 'ended',
            'ended_at' => $endedAt,
            'duration' => $duration,
        ]);

        // Update all participant attendance scores
        foreach ($session->participants as $participant) {
            if (is_null($participant->left_at)) {
                $participant->update(['left_at' => $endedAt]);
            }
            $participant->updateAttendanceScore();
        }

        Log::info("Session {$sessionId} ended. Duration: {$duration} minutes");

        // Broadcast session ended event
        $this->broadcaster->broadcastSessionEnded($sessionId);

        return $session->fresh();
    }

    /**
     * Auto-update session statuses based on current time.
     * - Ends live sessions that have passed their scheduled duration.
     * - Auto-starts scheduled sessions that have reached their start time.
     * - Marks scheduled sessions whose window has fully passed as ended.
     */
    public function autoUpdateStatuses(): void
    {
        $now = now();

        // 1. End live sessions whose scheduled end time has passed
        //    End time = started_at + duration (respects instructor's planned duration)
        $staleLive = Session::where('status', 'live')
            ->whereNotNull('started_at')
            ->whereNotNull('duration')
            ->with('participants')
            ->get()
            ->filter(fn (Session $s) => $now->greaterThan(
                $s->started_at->copy()->addMinutes($s->duration)
            ));

        foreach ($staleLive as $session) {
            // Update participants: set left_at and recalculate attendance
            foreach ($session->participants as $participant) {
                if (is_null($participant->left_at)) {
                    $participant->update(['left_at' => $now]);
                }
                $participant->updateAttendanceScore();
            }

            // Preserve original planned duration (don't overwrite with elapsed time)
            $session->update([
                'status'   => 'ended',
                'ended_at' => $now,
            ]);

            $this->broadcaster->broadcastSessionEnded($session->id);
            Log::info("Auto-ended session {$session->id} (reached scheduled end time)");
        }

        // 2. Auto-start scheduled sessions at their scheduled_at time
        $readyScheduled = Session::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereNotNull('duration')
            ->where('scheduled_at', '<=', $now)
            ->get()
            ->filter(fn (Session $s) => $now->lessThanOrEqualTo(
                $s->scheduled_at->copy()->addMinutes($s->duration)
            ));

        foreach ($readyScheduled as $session) {
            $session->update([
                'status'     => 'live',
                'started_at' => $session->scheduled_at, // start exactly at scheduled time
            ]);

            $this->broadcaster->broadcastSessionStarted($session->id);
            Log::info("Auto-started session {$session->id} at scheduled time");
        }

        // 3. Mark scheduled sessions whose entire window has passed as ended (never started)
        $expiredScheduled = Session::where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereNotNull('duration')
            ->get()
            ->filter(fn (Session $s) => $now->greaterThan(
                $s->scheduled_at->copy()->addMinutes($s->duration)
            ));

        foreach ($expiredScheduled as $session) {
            $session->update([
                'status'   => 'ended',
                'ended_at' => $now,
            ]);
            Log::info("Auto-ended session {$session->id} (never started, window passed)");
        }
    }

    /**
     * Get or create session token for a user
     */
    public function getSessionToken(string $sessionId, string $userId): array
    {
        $session = Session::findOrFail($sessionId);
        $user = User::findOrFail($userId);

        // Check if user is authorized to join
        $this->validateEnrollment($session, $user);

        // Determine if user is moderator
        $isModerator = $session->instructor_id === $userId || $user->isAdmin();

        // Generate JWT
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->profile_image ?? '',
        ];

        $token = $this->tokenService->generateToken($userData, $session->room_id, $isModerator);

        // Create or update participant record
        SessionParticipant::updateOrCreate(
            ['session_id' => $sessionId, 'user_id' => $userId],
            ['joined_at' => now()]
        );

        Log::info("Generated token for user {$userId} in session {$sessionId} (moderator: " . ($isModerator ? 'yes' : 'no') . ")");

        return [
            'token' => $token,
            'roomName' => $session->room_id,
            'isModerator' => $isModerator,
            'config' => $this->getJitsiConfig($session),
        ];
    }

    /**
     * Validate user enrollment in the session's course
     */
    private function validateEnrollment(Session $session, User $user): void
    {
        // Instructor of the session or admin can always join
        if ($session->instructor_id === $user->id || $user->isAdmin()) {
            Log::info("Session join authorized for user {$user->id} as instructor/admin", [
                'session_id' => $session->id,
                'user_id' => $user->id,
            ]);
            return;
        }

        // Student must be enrolled with approved status
        $enrollment = Enrollment::where('course_id', $session->course_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$enrollment) {
            Log::warning("Session join denied: user not enrolled in course", [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'course_id' => $session->course_id,
            ]);
            throw new \Exception('User is not enrolled in this course');
        }

        // Allow active, enrolled, or approved statuses
        $allowedStatuses = ['active', 'enrolled', 'approved'];
        if (!in_array(strtolower($enrollment->status), $allowedStatuses, true)) {
            Log::warning("Session join denied: invalid enrollment status", [
                'session_id' => $session->id,
                'user_id' => $user->id,
                'course_id' => $session->course_id,
                'enrollment_status' => $enrollment->status,
                'allowed_statuses' => $allowedStatuses,
            ]);
            throw new \Exception('Your enrollment in this course is not active. Current status: ' . $enrollment->status);
        }

        Log::info("Session join authorized for enrolled student", [
            'session_id' => $session->id,
            'user_id' => $user->id,
            'course_id' => $session->course_id,
            'enrollment_status' => $enrollment->status,
        ]);
    }

    /**
     * Get Jitsi configuration based on session settings
     */
    private function getJitsiConfig(Session $session): array
    {
        return [
            'startWithAudioMuted' => $session->start_muted,
            'startWithVideoMuted' => $session->start_video_off,
            'disableDeepLinking' => true,
            'p2p' => ['enabled' => false], // Always use JVB for LMS
            'prejoinPageEnabled' => $session->waiting_room,
            'disableModeratorIndicator' => false,
            'hideLobbyButton' => false,
            'enableLobbyChat' => $session->chat_enabled,
            'raiseHandEnabled' => $session->raise_hand_enabled,
            'enableFeaturesBasedOnToken' => true,
            'lockRoomGuestEnabled' => true,
        ];
    }

    /**
     * Get live participant count
     */
    public function getLiveParticipantCount(string $sessionId): int
    {
        return SessionParticipant::where('session_id', $sessionId)
            ->whereNull('left_at')
            ->count();
    }

    /**
     * Mark participant as left
     */
    public function participantLeft(string $sessionId, string $userId): void
    {
        $participant = SessionParticipant::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if ($participant) {
            $participant->update(['left_at' => now()]);
            Log::info("User {$userId} left session {$sessionId}");
        }
    }

    /**
     * Update participant engagement metrics
     */
    public function updateParticipantMetrics(string $sessionId, string $userId, array $metrics): void
    {
        $participant = SessionParticipant::where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if ($participant) {
            $updateData = [];
            if (isset($metrics['mic_active'])) {
                $updateData['mic_active'] = $metrics['mic_active'];
            }
            if (isset($metrics['camera_active'])) {
                $updateData['camera_active'] = $metrics['camera_active'];
            }
            if (isset($metrics['hands_raised'])) {
                $updateData['hands_raised'] = $participant->hands_raised + $metrics['hands_raised'];
            }
            if (isset($metrics['chat_messages'])) {
                $updateData['chat_messages'] = $participant->chat_messages + $metrics['chat_messages'];
            }

            $participant->update($updateData);
        }
    }
}
