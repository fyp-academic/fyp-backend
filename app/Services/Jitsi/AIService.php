<?php

namespace App\Services\Jitsi;

use App\Models\Session;
use App\Models\SessionTranscript;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class AIService
{
    private string $model;
    private string $summaryModel;
    private SocketBroadcaster $broadcaster;

    public function __construct()
    {
        $this->model = config('services.openai.model', 'whisper-1');
        $this->summaryModel = config('services.openai.summary_model', 'gpt-4o');
        $this->broadcaster = new SocketBroadcaster();
    }

    /**
     * Transcribe an audio chunk using OpenAI Whisper
     */
    public function transcribeChunk(string $audioPath, string $sessionId, string $userId): SessionTranscript
    {
        $session = Session::findOrFail($sessionId);
        $user = \App\Models\User::findOrFail($userId);

        try {
            // Check if OpenAI client is available
            if (!class_exists(\OpenAI\Client::class)) {
                throw new \Exception('OpenAI client not installed. Run: composer require openai-php/client openai-php/laravel');
            }

            $response = OpenAI::audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($audioPath, 'r'),
                'language' => 'en',
                'response_format' => 'verbose_json',
                'timestamp_granularities' => ['segment'],
            ]);

            $result = $response->toArray();

            // Save to database
            $transcript = SessionTranscript::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'text' => $result['text'],
                'segments' => $result['segments'] ?? [],
                'speaker_name' => $user->name,
                'timestamp' => now(),
            ]);

            // Broadcast via Socket.io
            $this->broadcaster->broadcastTranscript($sessionId, [
                'id' => $transcript->id,
                'speaker' => $transcript->speaker_name,
                'text' => $transcript->text,
                'segments' => $transcript->segments,
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
     * Generate AI summary of the session using GPT-4
     */
    public function summariseSession(string $sessionId): string
    {
        $session = Session::findOrFail($sessionId);

        // Get all transcripts
        $transcripts = $session->transcripts;

        if ($transcripts->isEmpty()) {
            Log::info("No transcripts found for session {$sessionId}, skipping summarization");
            return '';
        }

        // Build full transcript text
        $fullText = $transcripts->map(fn ($t) => "[{$t->speaker_name}]: {$t->text}")->join("\n");

        // Truncate if too long (GPT-4 has token limits)
        $maxChars = 50000;
        $truncatedText = strlen($fullText) > $maxChars ? substr($fullText, 0, $maxChars) . "\n[...truncated]" : $fullText;

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->summaryModel,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an LMS assistant. Summarise this lecture transcript. Output: 1) Key topics covered 2) Action items 3) Questions raised 4) Concepts to review. Be concise and student-focused.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $truncatedText,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

            $summary = $response->choices[0]->message->content;

            // Save to session
            $session->update(['ai_summary' => $summary]);

            // Send notifications
            $this->notifySummaryAvailable($session);

            Log::info("Generated summary for session {$sessionId}");

            return $summary;

        } catch (\Exception $e) {
            Log::error("Failed to generate summary for session {$sessionId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Answer a question using the session transcript as context
     */
    public function answerQuestion(string $sessionId, string $question): string
    {
        $session = Session::findOrFail($sessionId);

        // Get transcript context (last 8000 chars)
        $fullText = $session->getFullTranscriptText();
        $context = strlen($fullText) > 8000 ? '...' . substr($fullText, -8000) : $fullText;

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "You are a live course assistant. Answer based on what was discussed in the session. Context:\n{$context}",
                    ],
                    [
                        'role' => 'user',
                        'content' => $question,
                    ],
                ],
                'max_tokens' => 300,
                'temperature' => 0.4,
            ]);

            return $response->choices[0]->message->content;

        } catch (\Exception $e) {
            Log::error("Failed to answer question for session {$sessionId}: " . $e->getMessage());
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
     * Get engagement sentiment analysis from chat messages
     */
    public function analyzeEngagement(array $chatMessages): array
    {
        if (empty($chatMessages)) {
            return ['sentiment' => 'neutral', 'engagement_score' => 50];
        }

        $text = implode("\n", array_slice($chatMessages, -20)); // Last 20 messages

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Analyze the sentiment and engagement level of these chat messages from a live session. Return only a JSON object with keys: sentiment (positive/neutral/negative), engagement_score (0-100), key_concerns (array of strings or empty).',
                    ],
                    [
                        'role' => 'user',
                        'content' => $text,
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 200,
            ]);

            $content = $response->choices[0]->message->content;

            // Try to parse JSON from response
            if (preg_match('/\{.*\}/s', $content, $matches)) {
                return json_decode($matches[0], true) ?? ['sentiment' => 'neutral', 'engagement_score' => 50];
            }

            return ['sentiment' => 'neutral', 'engagement_score' => 50];
        } catch (\Exception $e) {
            Log::error('Engagement analysis failed', ['error' => $e->getMessage()]);
            return ['sentiment' => 'neutral', 'engagement_score' => 50];
        }
    }

    /**
     * Generate quiz questions from session transcript using GPT-4o
     * Returns 5 multiple choice questions based on content
     */
    public function generateQuizFromTranscript(string $sessionId): array
    {
        $session = Session::findOrFail($sessionId);
        $transcripts = $session->transcripts;

        if ($transcripts->isEmpty()) {
            throw new \Exception('No transcript available for quiz generation');
        }

        // Build transcript text
        $fullText = $transcripts->map(fn ($t) => "[{$t->speaker_name}]: {$t->text}")->join("\n");

        // Truncate if too long
        $maxChars = 10000;
        $truncatedText = strlen($fullText) > $maxChars
            ? substr($fullText, 0, $maxChars) . "\n[...session continues...]"
            : $fullText;

        try {
            $response = OpenAI::chat()->create([
                'model' => $this->summaryModel,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Generate exactly 5 multiple choice questions based on the provided session transcript. Return a JSON array with objects containing: question (string), options (array of 4 strings), correctAnswer (integer 0-3), explanation (string). Make questions test understanding of key concepts discussed.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Session: {$session->title}\n\nTranscript:\n{$truncatedText}",
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);

            $content = $response->choices[0]->message->content;

            // Extract JSON from response
            if (preg_match('/\[.*\]/s', $content, $matches)) {
                $questions = json_decode($matches[0], true);
                
                if (!is_array($questions) || count($questions) !== 5) {
                    throw new \Exception('Invalid quiz format generated');
                }

                $quizId = 'quiz_' . substr($sessionId, 0, 8) . '_' . time();

                // Store quiz in database (would create Quiz model)
                // For now, return the generated quiz

                return [
                    'quiz_id' => $quizId,
                    'session_id' => $sessionId,
                    'title' => "Quiz: {$session->title}",
                    'questions' => $questions,
                    'generated_at' => now()->toIso8601String(),
                    'url' => "/quizzes/{$quizId}",
                ];
            }

            throw new \Exception('Failed to parse quiz questions from AI response');

        } catch (\Exception $e) {
            Log::error("Failed to generate quiz for session {$sessionId}: " . $e->getMessage());
            throw new \Exception('Failed to generate quiz: ' . $e->getMessage());
        }
    }
}
