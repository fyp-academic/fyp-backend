<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAdaptationService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) (config('services.gemini.api_key') ?? '');
        $this->model = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
        $this->baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    public function adapt(string $originalText, array $studentProfile, array $instructorSettings): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('GeminiAdaptationService: Missing Gemini API key');
            return null;
        }

        $systemPrompt = $this->systemPrompt();
        $userPrompt = $this->buildUserPrompt($originalText, $studentProfile, $instructorSettings);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userPrompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 400,
                'topP' => 0.8,
            ],
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
        ];

        try {
            $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
            $response = Http::timeout(30)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->failed()) {
                Log::error('Gemini adaptation API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $text = $response->json('candidates.0.content.parts.0.text');

            if (empty($text)) {
                Log::warning('Gemini adaptation returned empty text');
                return null;
            }

            return trim($text);
        } catch (\Throwable $e) {
            Log::error('Gemini adaptation exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function systemPrompt(): string
    {
        return <<<TXT
You are an adaptive instructional tutor embedded in a Learning Management System.

Your ONLY job is to rewrite the instructor's original content to match the student's learning profile.

STRICT RULES:
1. DO NOT add any facts, concepts, or information not present in the original content.
2. DO NOT change the meaning, accuracy, or learning objective of the original content.
3. DO NOT rewrite, hint at, or paraphrase any assessment or graded questions.
4. DO NOT fabricate examples from unrelated domains.
5. ALWAYS ground your entire response on the original instructor content provided to you.
6. Keep all technical definitions word-for-word exact. Only simplify the surrounding explanation.
7. If the original content is already appropriate for this student's profile, return it with minimal changes.
8. Never invent prerequisites, references, or external resources not mentioned in the original.
9. Output the adapted explanation only. No preamble. No "Here is the adapted version:". Just the content.
TXT;
    }

    private function buildUserPrompt(string $originalText, array $profile, array $settings): string
    {
        $pace = $profile['pace'] ?? 'medium';
        $quizAverage = $profile['quiz_average'] ?? 0;
        $weakTopics = implode(', ', $profile['weak_topics'] ?? []);
        $modality = $profile['preferred_modality'] ?? 'text';
        $completionRate = $profile['completion_rate'] ?? 0;

        $allowSimplification = ($settings['allow_simplification'] ?? true) ? 'yes' : 'no';
        $allowExampleSubstitution = ($settings['allow_example_substitution'] ?? true) ? 'yes' : 'no';
        $allowAnalogies = ($settings['allow_analogies'] ?? true) ? 'yes' : 'no';
        $lockTechnicalDefinitions = ($settings['lock_technical_definitions'] ?? true) ? 'yes' : 'no';
        $maxDifficulty = $settings['max_difficulty'] ?? 5;

        return <<<TXT
ORIGINAL INSTRUCTOR CONTENT:
"""
{$originalText}
"""

STUDENT LEARNING PROFILE:
- Learning Pace: {$pace}
  (slow = needs more scaffolding and numbered steps | medium = standard | fast = compress basics, go to key insight)
- Quiz Average: {$quizAverage}%
  (below 60% = student is struggling, use simpler language | above 80% = student is strong, can handle depth)
- Weak Topics: {$weakTopics}
- Preferred Modality: {$modality}
  (visual = suggest diagram/table layout in text | text = clean prose | example-based = lead with real-world example first)
- Completion Rate: {$completionRate}%

INSTRUCTOR ADAPTATION PERMISSIONS:
- Simplification allowed: {$allowSimplification}
- Example substitution allowed: {$allowExampleSubstitution}
- Analogies allowed: {$allowAnalogies}
- Technical definitions must remain word-for-word exact: {$lockTechnicalDefinitions}
- Max adaptation depth (1=light touch, 5=full rewrite): {$maxDifficulty}

TASK:
Rewrite the above instructor content for this specific student only.
Apply the student profile and instructor permissions strictly.
Same knowledge. Same objective. Different delivery.
Maximum 250 words.
TXT;
    }
}
