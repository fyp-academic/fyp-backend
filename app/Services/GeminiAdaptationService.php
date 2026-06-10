<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAdaptationService
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 500;

    private const TIMEOUT_SECONDS = 60;

    public function __construct()
    {
        $this->apiKey = (string) (config('services.gemini.api_key') ?? '');
        $this->model = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
        $this->baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    /**
     * Rewrite delivery only. Instructor source in DB is never modified.
     * Includes retry logic with exponential backoff for resilience.
     *
     * @param  array{lesson_summary?: string, semantic_role?: string, key_terms?: string[], position_pct?: int, total_chunks?: int, chunk_index?: int, presentation_mode?: string}  $lessonContext
     */
    public function adapt(
        string $originalText,
        array $studentProfile,
        array $instructorSettings,
        array $lessonContext = [],
    ): ?string
    {
        if (empty($this->apiKey)) {
            Log::error('GeminiAdaptationService: Missing Gemini API key');

            return null;
        }

        if (trim($originalText) === '') {
            return null;
        }

        $systemPrompt = $this->systemPrompt();
        $userPrompt = $this->buildUserPrompt($originalText, $studentProfile, $instructorSettings, $lessonContext);
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

        // Attempt with retry logic
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
                $response = Http::timeout(self::TIMEOUT_SECONDS)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($url, $payload);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');

                    if (!empty($text)) {
                        Log::info('Gemini adaptation successful', [
                            'attempt' => $attempt,
                            'profile_knowledge_level' => $studentProfile['knowledge_level'] ?? 'unknown',
                            'profile_pace' => $studentProfile['pace'] ?? 'unknown',
                            'text_length' => strlen($originalText),
                            'output_length' => strlen($text),
                        ]);

                        return trim($text);
                    }

                    // Empty response
                    Log::warning('Gemini adaptation returned empty text', [
                        'attempt' => $attempt,
                        'response_status' => $response->status(),
                    ]);

                    return null;
                }

                // Failed response - check if retryable
                $status = $response->status();
                $isRetryable = $this->isRetryableError($status);

                Log::warning('Gemini adaptation API error', [
                    'attempt' => $attempt,
                    'status' => $status,
                    'is_retryable' => $isRetryable,
                    'max_attempts' => self::MAX_RETRIES,
                    'body' => substr($response->body(), 0, 200),
                ]);

                // If not retryable or last attempt, give up
                if (!$isRetryable || $attempt === self::MAX_RETRIES) {
                    return null;
                }

                // Wait before retry with exponential backoff
                $delay = self::RETRY_DELAY_MS * (2 ** ($attempt - 1));
                usleep($delay * 1000); // Convert ms to microseconds
            } catch (\ConnectException $e) {
                // Network error - retryable
                Log::warning('Gemini adaptation connection error', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'max_attempts' => self::MAX_RETRIES,
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * (2 ** ($attempt - 1));
                    usleep($delay * 1000);
                    continue;
                }

                return null;
            } catch (\Throwable $e) {
                // Generic exception
                Log::error('Gemini adaptation exception', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                ]);

                return null;
            }
        }

        return null;
    }

    /**
     * Determine if an HTTP status code represents a retryable error
     */
    private function isRetryableError(int $status): bool
    {
        // Retryable: server errors, timeouts, rate limits
        return in_array($status, [408, 429, 500, 502, 503, 504], true);
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
10. Never repeat scaffolding, context, or term definitions already established in earlier chunks of the same lesson. The LESSON CONTEXT block (when present) tells you what came before — honour it.
TXT;
    }

    private function buildUserPrompt(string $originalText, array $profile, array $settings, array $lessonContext = []): string
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

        // Build optional lesson context block
        $lessonContextBlock = '';
        if (! empty($lessonContext)) {
            $lessonSummary = (string) ($lessonContext['lesson_summary'] ?? '');
            $semanticRole  = (string) ($lessonContext['semantic_role'] ?? '');
            $positionPct   = (int)    ($lessonContext['position_pct'] ?? 0);
            $priorTerms    = implode(', ', array_map('strval', $lessonContext['key_terms'] ?? [])) ?: 'none';
            $chunkIndex    = (int) ($lessonContext['chunk_index'] ?? 0);
            $totalChunks   = (int) ($lessonContext['total_chunks'] ?? 1);

            $roleNote = $semanticRole === 'summary'
                ? 'This chunk closes the lesson — consolidate, do not re-explain concepts.'
                : ($semanticRole === 'introduction'
                    ? 'This chunk opens the lesson — set up context without anticipating later sections.'
                    : '');

            $lessonContextBlock = <<<CTX

LESSON CONTEXT (for continuity — do not repeat what was already established):
- Lesson overview: {$lessonSummary}
- This chunk role: {$semanticRole} (chunk {$chunkIndex} of {$totalChunks}, {$positionPct}% through lesson)
- Terms already introduced in prior chunks: {$priorTerms}
- Continuity instruction: Do NOT re-scaffold or re-define any term from the list above.{$roleNote}
CTX;
        }

        // Build optional presentation mode block
        $presentationModeBlock = '';
        $presentationMode = (string) ($lessonContext['presentation_mode'] ?? '');
        if ($presentationMode !== '') {
            $presentationModeBlock = match ($presentationMode) {
                'guided_steps' => <<<'PM'

PRESENTATION MODE: guided_steps
- Structure the output as numbered steps where logically appropriate.
- Bold key technical terms on first mention: **term**.
- Mark the single most important concept per step with ==highlight== syntax.
- Use one idea per sentence; add short transitional phrases between steps.
- Apply Mayer's Signaling: begin each major step with a directional cue (e.g. "First, ...", "Next, ...", "Finally, ...").
PM,
                'visual_discovery' => <<<'PM'

PRESENTATION MODE: visual_discovery
- Prefer markdown tables and section headings over dense prose where the content allows.
- Group related concepts under clear headings (## or ###).
- Bold key terms within sentences.
- Use bullet lists for enumerations of 3 or more items.
- Apply Mayer's Signaling: use headings as visual anchors directing learner attention.
PM,
                'deep_focus' => <<<'PM'

PRESENTATION MODE: deep_focus
- Write in dense academic prose; avoid unnecessary step numbering or bullet lists.
- Surface logical connections between ideas stated in the original using connective language (therefore, consequently, which implies, etc.).
- Compress any redundant phrasing from the original.
- Apply Mayer's Signaling only for the single most critical concept (bold once).
PM,
                'narrative_example' => <<<'PM'

PRESENTATION MODE: narrative_example
- If an example or scenario exists in the original, lead with it before the theoretical explanation.
- Use a --- markdown separator between the example/scenario and the theory section.
- Write in a conversational explanatory tone.
- Ensure the theory section explicitly refers back to the opening example.
PM,
                default => '',
            };
        }

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
{$lessonContextBlock}{$presentationModeBlock}
TASK:
Produce a student delivery copy. Same learning objective. Same facts. Different phrasing/structure only.
Target length: about {$wordBudget} words (never exceed 150% of original word count).
TXT;
    }
}
