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

    /**
     * Rewrite delivery only. Instructor source in DB is never modified.
     */
    public function adapt(string $originalText, array $studentProfile, array $instructorSettings): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('GeminiAdaptationService: Missing Gemini API key');

            return null;
        }

        if (trim($originalText) === '') {
            return null;
        }

        $systemPrompt = $this->systemPrompt();
        $userPrompt = $this->buildUserPrompt($originalText, $studentProfile, $instructorSettings);
        $maxTokens = $this->resolveMaxTokens($originalText, $instructorSettings);

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userPrompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => $maxTokens,
                'topP' => 0.75,
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

    private function resolveMaxTokens(string $originalText, array $settings): int
    {
        $wordCount = str_word_count(strip_tags($originalText));
        $maxDifficulty = (int) ($settings['max_difficulty'] ?? 5);
        $scaled = (int) min(800, max(200, $wordCount * (2 + ($maxDifficulty * 0.3))));

        return $scaled;
    }

    private function systemPrompt(): string
    {
        return <<<'TXT'
You are a delivery-layer rewriter inside a Learning Management System (LMS).

SCOPE — CONTENT ADAPTATION ONLY:
- You adapt HOW the instructor's text is expressed (order, clarity, scaffolding, emphasis).
- You do NOT control page layout, fonts, colours, or navigation (the LMS UI handles presentation and navigation separately).
- Never claim you changed layout or navigation.

INSTRUCTOR SOURCE IS IMMUTABLE:
- The instructor's stored lesson is never edited by you.
- You produce a separate student-facing delivery copy derived only from the provided original.
- Learning objectives, facts, scope, and assessments must remain equivalent to the original.

STRICT RULES:
1. Use ONLY information present in the original content. No new facts, topics, examples, URLs, or citations.
2. Do not change the pedagogical objective or assessment intent.
3. Never rewrite, hint at, or paraphrase quiz/assessment questions.
4. Preserve technical terms and definitions exactly as written unless instructor permissions allow simplification of surrounding prose only.
5. If the original already fits the student profile, return it with minimal or no changes.
6. Output markdown body only — no preamble, labels, or meta commentary.
7. For "visual" delivery: reorganize existing sentences into markdown tables or bullet lists using existing words only — do not invent diagram content.
8. For "example-based" delivery: lead with an example only if one already exists in the original; otherwise use clearest existing illustration.
9. "Advanced depth" means surface relationships between concepts already stated — never introduce new concepts.
TXT;
    }

    private function buildUserPrompt(string $originalText, array $profile, array $settings): string
    {
        $pace = $profile['pace'] ?? 'medium';
        $quizAverage = $profile['quiz_average'] ?? 0;
        $weakTopics = implode(', ', $profile['weak_topics'] ?? []) ?: 'none identified';
        $modality = $profile['preferred_modality'] ?? 'text';
        $completionRate = $profile['completion_rate'] ?? 0;
        $knowledgeLevel = $profile['knowledge_level'] ?? 'intermediate';
        $primaryProfile = $profile['primary_profile'] ?? 'unknown';
        $vark = $profile['vark_style'] ?? 'unknown';
        $revisitRate = $profile['revisit_rate'] ?? 'unknown';
        $learningDelta = $profile['quiz_learning_delta'] ?? 'unknown';
        $atRisk = ($profile['at_risk'] ?? false) ? 'yes' : 'no';
        $minDifficulty = (int) ($settings['min_difficulty'] ?? 1);
        $maxDifficulty = (int) ($settings['max_difficulty'] ?? 5);

        $allowSimplification = ($settings['allow_simplification'] ?? true) ? 'yes' : 'no';
        $allowExampleSubstitution = ($settings['allow_example_substitution'] ?? true) ? 'yes' : 'no';
        $allowAnalogies = ($settings['allow_analogies'] ?? true) ? 'yes' : 'no';
        $lockTechnicalDefinitions = ($settings['lock_technical_definitions'] ?? true) ? 'yes' : 'no';

        $depthInstruction = match ($knowledgeLevel) {
            'advanced' => 'Emphasize connections between ideas already in the original. Compress repetition. Do not add new material.',
            'novice' => 'Add scaffolding using only original wording: short numbered steps, inline clarifications of terms already defined in the original.',
            default => 'Balance clarity and brevity using only the original material.',
        };

        $paceInstruction = match ($pace) {
            'slow' => 'Expand transitions; use numbered steps where helpful; one idea per sentence.',
            'fast' => 'Remove redundancy; lead with the key takeaway; keep only essential detail from the original.',
            default => 'Standard clarity; preserve original structure where possible.',
        };

        $modalityInstruction = match ($modality) {
            'visual' => 'Prefer markdown tables or bullet lists built from existing sentences. No new visual elements or data.',
            'example-based' => 'If an example exists in the original, place it first; otherwise keep prose order.',
            default => 'Clean prose; preserve paragraph flow from the original.',
        };

        $wordBudget = (int) min(400, max(80, (int) (str_word_count(strip_tags($originalText)) * 1.25)));

        return <<<TXT
ORIGINAL INSTRUCTOR CONTENT (authoritative — do not alter meaning):
"""
{$originalText}
"""

STUDENT PROFILE (delivery tuning only):
- Knowledge level: {$knowledgeLevel}
- Pace: {$pace} — {$paceInstruction}
- Quiz average: {$quizAverage}%
- Weak topics: {$weakTopics}
- Delivery modality: {$modality} — {$modalityInstruction}
- Completion rate: {$completionRate}%
- HATC profile: {$primaryProfile} | VARK: {$vark}
- Revisit rate: {$revisitRate} | Quiz learning delta: {$learningDelta}
- At-risk: {$atRisk}

DEPTH GUIDANCE:
{$depthInstruction}

INSTRUCTOR PERMISSIONS:
- Simplification: {$allowSimplification}
- Example substitution (existing examples only): {$allowExampleSubstitution}
- Analogies (from original concepts only): {$allowAnalogies}
- Lock technical definitions word-for-word: {$lockTechnicalDefinitions}
- Adaptation depth: {$minDifficulty}–{$maxDifficulty} (1=minimal rephrase, 5=full delivery rewrite; never add facts)

TASK:
Produce a student delivery copy. Same learning objective. Same facts. Different phrasing/structure only.
Target length: about {$wordBudget} words (never exceed 150% of original word count).
TXT;
    }
}
