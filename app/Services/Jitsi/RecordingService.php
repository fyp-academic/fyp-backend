<?php

namespace App\Services\Jitsi;

use App\Models\Session;
use App\Models\SessionRecording;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecordingService
{
    private string $jibriUrl;
    private string $jitsiDomain;
    private JitsiTokenService $tokenService;

    public function __construct(JitsiTokenService $tokenService)
    {
        $this->jibriUrl = config('services.jitsi.jibri_url', 'http://localhost:2222');
        $this->jitsiDomain = config('services.jitsi.domain', env('JITSI_DOMAIN', 'meet.yourlms.com'));
        $this->tokenService = $tokenService;
    }

    /**
     * Start recording a session via Jibri
     */
    public function startRecording(string $sessionId, string $instructorId): SessionRecording
    {
        $session = Session::findOrFail($sessionId);

        if ($session->instructor_id !== $instructorId) {
            throw new \Exception('Only the session instructor can start recording');
        }

        if (!$session->isLive()) {
            throw new \Exception('Session must be live to start recording');
        }

        // Create recording record
        $recording = SessionRecording::create([
            'session_id' => $sessionId,
            's3_key' => '',
            'status' => 'pending',
        ]);

        // Generate a recorder token
        $recorderToken = $this->tokenService->generateToken([
            'id' => 'recorder',
            'name' => 'Recorder',
            'email' => 'recorder@system.local',
            'avatar' => '',
        ], $session->room_id, true);

        // Call Jibri REST API to start recording
        try {
            $response = Http::timeout(30)->post("{$this->jibriUrl}/jibri/api/v1.0/broadcast", [
                'sessionId' => $session->room_id,
                'callParams' => [
                    'callUrlInfo' => [
                        'baseUrl' => "https://{$this->jitsiDomain}",
                        'callName' => $session->room_id,
                    ],
                    'token' => $recorderToken,
                    'user' => [
                        'name' => 'LMS Recorder',
                    ],
                ],
                'sinkType' => 'file',
                'filename' => "session_{$sessionId}_{$recording->id}_" . now()->format('Y-m-d_H-i-s') . '.mp4',
            ]);

            if ($response->successful()) {
                Log::info("Started recording for session {$sessionId}, recording ID: {$recording->id}");
                return $recording;
            }

            $recording->markAsFailed();
            throw new \Exception('Failed to start recording: ' . $response->body());

        } catch (\Exception $e) {
            $recording->markAsFailed();
            Log::error("Failed to start recording for session {$sessionId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Stop recording via Jibri REST API
     */
    public function stopRecording(string $sessionId, string $instructorId): void
    {
        $session = Session::findOrFail($sessionId);

        if ($session->instructor_id !== $instructorId) {
            throw new \Exception('Only the session instructor can stop recording');
        }

        // Find pending/processing recording
        $recording = SessionRecording::where('session_id', $sessionId)
            ->whereIn('status', ['pending', 'processing'])
            ->latest()
            ->first();

        if (!$recording) {
            throw new \Exception('No active recording found for this session');
        }

        try {
            $response = Http::timeout(30)->post("{$this->jibriUrl}/jibri/api/v1.0/stop", [
                'sessionId' => $session->room_id,
            ]);

            if ($response->successful()) {
                $recording->update(['status' => 'processing']);
                Log::info("Stopped recording for session {$sessionId}");
            } else {
                throw new \Exception('Failed to stop recording: ' . $response->body());
            }

        } catch (\Exception $e) {
            Log::error("Failed to stop recording for session {$sessionId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle webhook from Jibri when recording is complete
     */
    public function handleRecordingWebhook(array $data): SessionRecording
    {
        $sessionId = $data['sessionId'] ?? null;
        $recordingId = $data['recordingId'] ?? null;
        $filePath = $data['file'] ?? null;

        if (!$sessionId || !$filePath) {
            throw new \Exception('Invalid webhook data: missing sessionId or file');
        }

        // Find the recording
        $recording = SessionRecording::find($recordingId);
        if (!$recording) {
            // Try to find by session and pending status
            $recording = SessionRecording::where('session_id', $sessionId)
                ->where('status', 'processing')
                ->latest()
                ->first();
        }

        if (!$recording) {
            throw new \Exception('Recording not found for session ' . $sessionId);
        }

        try {
            // Upload to S3
            $s3Key = $this->uploadToS3($filePath, $sessionId, $recording->id);

            // Get file stats
            $fileSize = filesize($filePath);

            // Update recording
            $recording->update([
                's3_key' => $s3Key,
                'status' => 'completed',
                'size' => $fileSize,
            ]);

            // Update session recording URL
            $recording->session->update([
                'recording_url' => $recording->getDownloadUrl(),
            ]);

            // Send notifications to participants
            $this->notifyRecordingAvailable($recording);

            // Clean up local file
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            Log::info("Recording processed for session {$sessionId}, S3 key: {$s3Key}");

            return $recording;

        } catch (\Exception $e) {
            $recording->markAsFailed();
            Log::error("Failed to process recording for session {$sessionId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upload recording file to S3
     */
    private function uploadToS3(string $filePath, string $sessionId, string $recordingId): string
    {
        $s3Key = "recordings/{$sessionId}/{$recordingId}/" . basename($filePath);

        $fileContents = file_get_contents($filePath);
        Storage::disk('s3')->put($s3Key, $fileContents, 'private');

        return $s3Key;
    }

    /**
     * Get recording status
     */
    public function getRecordingStatus(string $sessionId): ?SessionRecording
    {
        return SessionRecording::where('session_id', $sessionId)
            ->latest()
            ->first();
    }

    /**
     * Check if Jibri is healthy
     */
    public function isJibriHealthy(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->jibriUrl}/jibri/api/v1.0/health");
            return $response->successful() && ($response->json('status') === 'healthy' || $response->json('status') === 'idle');
        } catch (\Exception $e) {
            Log::error('Jibri health check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification that recording is available
     */
    private function notifyRecordingAvailable(SessionRecording $recording): void
    {
        $session = $recording->session;
        $course = $session->course;

        // Notify instructor
        $instructor = $session->instructor;
        if ($instructor) {
            $instructor->notifications()->create([
                'type' => 'recording_available',
                'title' => 'Session Recording Ready',
                'body' => "The recording for '{$session->title}' is now available.",
                'payload' => [
                    'session_id' => $session->id,
                    'recording_id' => $recording->id,
                    'download_url' => $recording->getDownloadUrl(),
                ],
            ]);
        }

        // Notify enrolled students
        foreach ($course->enrollments()->where('status', 'active')->get() as $enrollment) {
            $enrollment->user->notifications()->create([
                'type' => 'recording_available',
                'title' => 'Session Recording Available',
                'body' => "The recording for '{$session->title}' in '{$course->title}' is now available.",
                'payload' => [
                    'session_id' => $session->id,
                    'course_id' => $course->id,
                    'recording_url' => $recording->getDownloadUrl(),
                ],
            ]);
        }
    }
}
