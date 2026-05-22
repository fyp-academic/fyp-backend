<?php

namespace App\Services\Jitsi;

use App\Models\Session;
use App\Models\SessionTranscript;
use App\Services\GeminiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    private SocketBroadcaster $broadcaster;
    private GeminiService $gemini;

    public function __construct()
    {
        $this->broadcaster = new SocketBroadcaster();
        $this->gemini = new GeminiService();
    }

    /**
     * Transcribe an audio chunk using Gemini multimodal audio
     */
    public function transcribeChunk(string $audioPath, string $sessionId, string $userId): SessionTranscript
    {
        $session = Session::findOrFail($sessionId);
        $user = \App\Models\User::findOrFail($userId);

        try {
            $audioData = base64_encode(file_get_contents($audioPath));
            $mimeType = $this->detectAudioMimeType($audioPath);

            $apiKey = config('services.gemini.api_key') ?? env('GEMINI_API_KEY', '');
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent";

            $payload = [
                'contents' => [[
                    'parts' => [
                        ['text' => 'Transcribe this audio exactly. Return only the spoken words, no commentary, no timestamps.'],
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $audioData]],
                    ],
                ]],
                'generationConfig' => ['temperature' => 0.0, 'maxOutputTokens' => 2048],
            ];

            $response = Http::timeout(30)->post("{$endpoint}?key={$apiKey}", $payload);

            if ($response->failed()) {
                throw new \Exception('Gemini transcription failed: ' . $response->status());
            }

            $text = trim($response->json('candidates.0.content.parts.0.text', ''));

            if (empty($text)) {
                throw new \Exception('Empty transcription result');
            }

            $transcript = SessionTranscript::create([
                'session_id'   => $sessionId,
                'user_id'      => $userId,
                'text'         => $text,
                'segments'     => [],
                'speaker_name' => $user->name,
                'timestamp'    => now(),
            ]);

            $this->broadcaster->broadcastTranscript($sessionId, [
                'id'        => $transcript->id,
                'speaker'   => $transcript->speaker_name,
                'text'      => $transcript->text,
                'segments'  => [],
                'timestamp' => $transcript->timestamp->toIso8601String(),
            ]);

            Log::info("Transcribed chunk for session {$sessionId}, user {$userId}");

            return $transcript;

        } catch (\Exception $e) {
            Log::error("Failed to transcribe audio: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Detect MIME type for audio files
     */
    private function detectAudioMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match($ext) {
            'webm'       => 'audio/webm',
            'ogg'        => 'audio/ogg',
            'mp3'        => 'audio/mpeg',
            'wav'        => 'audio/wav',
            'm4a', 'mp4' => 'audio/mp4',
            default      => 'audio/webm',
        };
    }

    /**
     * Generate AI summary of the session using Gemini
     */
    public function summariseSession(string $sessionId): string
    {
        $session = Session::findOrFail($sessionId);
        $transcripts = $session->transcripts;

        if ($transcripts->isEmpty()) {
            Log::info("No transcripts found for session {$sessionId}, skipping summarization");
            return '';
        }

        $fullText = $transcripts->map(fn ($t) => "[{$t->speaker_name}]: {$t->text}")->join("\n");
        $truncatedText = mb_substr($fullText, 0, 50000) . (strlen($fullText) > 50000 ? "\n[...truncated]" : '');

        try {
            $system = 'You are an LMS assistant. Summarise this lecture transcript. '
                . 'Output: 1) Key topics covered 2) Action items 3) Questions raised 4) Concepts to review. '
                . 'Be concise and student-focused. Maximum 500 words.';

            $summary = $this->gemini->generate($truncatedText, $system, [
                'temperature' => 0.3,
                'max_tokens'  => 1000,
            ]);

            $session->update(['ai_summary' => $summary]);
            $this->notifySummaryAvailable($session);

            Log::info("Generated Gemini summary for session {$sessionId}");
            return $summary;

        } catch (\Exception $e) {
            Log::error("Failed to generate summary for session {$sessionId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Answer a question using the session transcript as context (Gemini)
     */
    public function answerQuestion(string $sessionId, string $question): string
    {
        $session = Session::findOrFail($sessionId);

        $fullText = method_exists($session, 'getFullTranscriptText')
            ? $session->getFullTranscriptText()
            : $session->transcripts->map(fn ($t) => "[{$t->speaker_name}]: {$t->text}")->join("\n");

        $context = mb_strlen($fullText) > 8000 ? '...' . mb_substr($fullText, -8000) : $fullText;

        $system = "You are a live course AI assistant. A student is asking a question during or after a session. "
            . "Answer helpfully and concisely based on the session transcript below. "
            . "If the topic wasn't covered, say so and provide a brief general answer.\n\nSession transcript:\n{$context}";

        try {
            return $this->gemini->generate($question, $system, [
                'temperature' => 0.4,
                'max_tokens'  => 400,
            ]);
        } catch (\Exception $e) {
            Log::error("Gemini failed to answer question for session {$sessionId}: " . $e->getMessage());
            return 'Sorry, I was unable to process your question at this time.';
        }
    }

    /**
     * Broadcast transcript update via Socket.io
     */
    private function broadcastTranscript(SessionTranscript $transcript): void
    {
        try {
            $this->broadcaster->broadcastTranscript($transcript->session_id, [
                'id' => $transcript->id,
                'speaker' => $transcript->speaker_name,
                'text' => $transcript->text,
                'segments' => $transcript->segments,
                'timestamp' => $transcript->timestamp->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            Log::debug("Could not broadcast transcript: " . $e->getMessage());
        }
    }

    /**
     * Notify participants that summary is available
     */
    private function notifySummaryAvailable(Session $session): void
    {
        $course = $session->course;

        // Notify instructor
        $instructor = $session->instructor;
        if ($instructor) {
            $instructor->notifications()->create([
                'type' => 'session_summary_ready',
                'title' => 'Session Summary Ready',
                'body' => "The AI summary for '{$session->title}' is now available.",
                'payload' => [
                    'session_id' => $session->id,
                    'course_id' => $course->id,
                ],
            ]);
        }

        // Notify students
        foreach ($course->enrollments()->where('status', 'active')->get() as $enrollment) {
            $enrollment->user->notifications()->create([
                'type' => 'session_summary_ready',
                'title' => 'Session Summary Available',
                'body' => "The AI summary for '{$session->title}' in '{$course->title}' is now available.",
                'payload' => [
                    'session_id' => $session->id,
                    'course_id' => $course->id,
                ],
            ]);
        }
    }

    /**
     * Get engagement sentiment analysis from chat messages (Gemini)
     */
    public function analyzeEngagement(array $chatMessages): array
    {
        if (empty($chatMessages)) {
            return ['sentiment' => 'neutral', 'engagement_score' => 50];
        }

        $text = implode("\n", array_slice($chatMessages, -20));

        $system = 'Analyze the sentiment and engagement level of these chat messages from a live session. '
            . 'Return ONLY a valid JSON object with keys: sentiment (positive/neutral/negative), '
            . 'engagement_score (0-100), key_concerns (array of strings or empty). No markdown, no explanation.';

        try {
            $raw = $this->gemini->generate($text, $system, ['temperature' => 0.3, 'max_tokens' => 200]);
            $raw = preg_replace('/```json\s*|\s*```/', '', $raw);
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                return json_decode($m[0], true) ?? ['sentiment' => 'neutral', 'engagement_score' => 50];
            }
            return ['sentiment' => 'neutral', 'engagement_score' => 50];
        } catch (\Exception $e) {
            Log::error('Gemini engagement analysis failed', ['error' => $e->getMessage()]);
            return ['sentiment' => 'neutral', 'engagement_score' => 50];
        }
    }

    /**
     * Generate quiz questions from session transcript using Gemini
     * Returns 5 multiple choice questions based on content
     */
    public function generateQuizFromTranscript(string $sessionId): array
    {
        $session = Session::findOrFail($sessionId);
        $transcripts = $session->transcripts;

        if ($transcripts->isEmpty()) {
            throw new \Exception('No transcript available for quiz generation');
        }

        $fullText = $transcripts->map(fn ($t) => "[{$t->speaker_name}]: {$t->text}")->join("\n");
        $truncatedText = mb_substr($fullText, 0, 10000) . (strlen($fullText) > 10000 ? "\n[...session continues...]" : '');

        $system = 'Generate exactly 5 multiple choice questions based on the provided session transcript. '
            . 'Return ONLY a valid JSON array (no markdown, no explanation) with objects containing: '
            . 'question (string), options (array of 4 strings), correctAnswer (integer 0-3), explanation (string). '
            . 'Start with [ and end with ].';

        try {
            $raw = $this->gemini->generate(
                "Session: {$session->title}\n\nTranscript:\n{$truncatedText}",
                $system,
                ['temperature' => 0.7, 'max_tokens' => 2000]
            );

            $raw = preg_replace('/```json\s*|\s*```/', '', $raw);

            if (preg_match('/\[.*\]/s', $raw, $m)) {
                $questions = json_decode($m[0], true);

                if (!is_array($questions) || count($questions) < 1) {
                    throw new \Exception('Invalid quiz format generated');
                }

                $quizId = 'quiz_' . substr($sessionId, 0, 8) . '_' . time();

                return [
                    'quiz_id'      => $quizId,
                    'session_id'   => $sessionId,
                    'title'        => "Quiz: {$session->title}",
                    'questions'    => $questions,
                    'generated_at' => now()->toIso8601String(),
                    'url'          => "/quizzes/{$quizId}",
                ];
            }

            throw new \Exception('Failed to parse quiz questions from Gemini response');

        } catch (\Exception $e) {
            Log::error("Failed to generate quiz for session {$sessionId}: " . $e->getMessage());
            throw new \Exception('Failed to generate quiz: ' . $e->getMessage());
        }
    }
}
