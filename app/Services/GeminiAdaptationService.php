<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAdaptationService
{
    private string $apiKey;

    private string $model;

    private string $baseUrl;

    /** 'gemini' (default) or 'claude'. Selects which LLM does the delivery rewrite. */
    private string $provider;

    private string $anthropicKey;

    private string $anthropicModel;

    private string $anthropicBaseUrl;

    private const MAX_RETRIES = 3;

    private const RETRY_DELAY_MS = 500;

    private const TIMEOUT_SECONDS = 60;

    /** When Gemini returns 429, pause adaptation calls this long to protect the shared quota. */
    private const RATE_LIMIT_COOLDOWN_KEY = 'gemini_adapt_cooldown';

    private const RATE_LIMIT_COOLDOWN_SECONDS = 60;

    /**
     * True when adaptation recently hit a Gemini rate limit. Callers should skip the rewrite and
     * serve the instructor original instead of piling on more calls (which would keep the quota
     * exhausted and starve the AI tutor).
     */
    public function isCoolingDown(): bool
    {
        return (bool) Cache::get(self::RATE_LIMIT_COOLDOWN_KEY, false);
    }

    public function __construct()
    {
        $this->apiKey = (string) (config('services.gemini.api_key') ?? '');
        $this->model = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
        $this->baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');

        $this->provider = (string) (config('services.adaptation.provider') ?? 'gemini');
        $this->anthropicKey = (string) (config('services.anthropic.api_key') ?? '');
        $this->anthropicModel = (string) (config('services.anthropic.model') ?? 'claude-haiku-4-5');
        $this->anthropicBaseUrl = rtrim((string) (config('services.anthropic.base_url') ?? 'https://api.anthropic.com/v1'), '/');
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
        if (trim($originalText) === '') {
            return null;
        }

        $apiKey = $this->provider === 'claude' ? $this->anthropicKey : $this->apiKey;
        if (empty($apiKey)) {
            Log::error('Adaptation: missing API key', ['provider' => $this->provider]);

            return null;
        }

        // Prompt building is provider-agnostic — only the HTTP transport below differs.
        $systemPrompt = $this->systemPrompt();
        $userPrompt = $this->buildUserPrompt($originalText, $studentProfile, $instructorSettings, $lessonContext);
        $maxTokens = $this->resolveMaxTokens($originalText, $instructorSettings);
        $originalLength = strlen($originalText);

        return $this->provider === 'claude'
            ? $this->callClaude($systemPrompt, $userPrompt, $maxTokens, $originalLength, $studentProfile)
            : $this->callGemini($systemPrompt, $userPrompt, $maxTokens, $originalLength, $studentProfile);
    }

    /**
     * Google Gemini transport (generateContent) with retry/backoff. Returns rewritten markdown or null.
     *
     * @param  array<string, mixed>  $studentProfile
     */
    private function callGemini(string $systemPrompt, string $userPrompt, int $maxTokens, int $originalLength, array $studentProfile): ?string
    {
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userPrompt]],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.4,
                'maxOutputTokens' => $maxTokens,
                'topP' => 0.85,
                // gemini-2.5-flash is a reasoning model whose "thinking" tokens count against
                // maxOutputTokens. Left on, thinking consumes the whole budget and the actual
                // rewrite is truncated (finishReason=MAX_TOKENS) → integrity rejects it as too
                // short → original served for every learner. This rewrite needs no deep
                // reasoning, so disable thinking and give the full budget to the output.
                'thinkingConfig' => ['thinkingBudget' => 0],
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
                            'text_length' => $originalLength,
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

                // Quota exhausted — open a short cooldown so subsequent requests serve the
                // original immediately instead of hammering Gemini and starving the AI tutor.
                if ($status === 429) {
                    Cache::put(self::RATE_LIMIT_COOLDOWN_KEY, true, now()->addSeconds(self::RATE_LIMIT_COOLDOWN_SECONDS));
                }

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
     * Anthropic Claude transport (Messages API) with retry/backoff. Same prompts as Gemini —
     * only the request/response shape differs. Returns rewritten markdown or null.
     *
     * @param  array<string, mixed>  $studentProfile
     */
    private function callClaude(string $systemPrompt, string $userPrompt, int $maxTokens, int $originalLength, array $studentProfile): ?string
    {
        $payload = [
            'model' => $this->anthropicModel,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ];

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = Http::timeout(self::TIMEOUT_SECONDS)->withHeaders([
                    'x-api-key' => $this->anthropicKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])->post("{$this->anthropicBaseUrl}/messages", $payload);

                if ($response->successful()) {
                    // content is an array of blocks; concatenate the text blocks.
                    $text = '';
                    foreach ((array) $response->json('content', []) as $block) {
                        if (($block['type'] ?? '') === 'text') {
                            $text .= (string) ($block['text'] ?? '');
                        }
                    }

                    if (trim($text) !== '') {
                        Log::info('Claude adaptation successful', [
                            'attempt' => $attempt,
                            'model' => $this->anthropicModel,
                            'profile_knowledge_level' => $studentProfile['knowledge_level'] ?? 'unknown',
                            'text_length' => $originalLength,
                            'output_length' => strlen($text),
                        ]);

                        return trim($text);
                    }

                    Log::warning('Claude adaptation returned empty/non-text response', [
                        'attempt' => $attempt,
                        'stop_reason' => $response->json('stop_reason'),
                    ]);

                    return null;
                }

                $status = $response->status();
                $isRetryable = $this->isRetryableError($status);

                if ($status === 429) {
                    Cache::put(self::RATE_LIMIT_COOLDOWN_KEY, true, now()->addSeconds(self::RATE_LIMIT_COOLDOWN_SECONDS));
                }

                Log::warning('Claude adaptation API error', [
                    'attempt' => $attempt,
                    'status' => $status,
                    'is_retryable' => $isRetryable,
                    'body' => substr($response->body(), 0, 200),
                ]);

                if (! $isRetryable || $attempt === self::MAX_RETRIES) {
                    return null;
                }

                usleep(self::RETRY_DELAY_MS * (2 ** ($attempt - 1)) * 1000);
            } catch (\Throwable $e) {
                Log::error('Claude adaptation exception', [
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
        // Retryable: server errors, timeouts, rate limits, overloaded (Anthropic 529)
        return in_array($status, [408, 429, 500, 502, 503, 504, 529], true);
    }

    private function resolveMaxTokens(string $originalText, array $settings): int
    {
        $wordCount = str_word_count(strip_tags($originalText));
        // Generous output budget so enriched output (advanced ~2.4x original, plus Socratic
        // prompts/scenarios and markdown) never hits MAX_TOKENS and gets truncated. With
        // thinking disabled the whole budget is available for the visible rewrite. ~1 word ≈
        // 1.4 tokens; budget ~6 tokens/word covers a 2.4x expansion with comfortable headroom.
        $scaled = (int) min(2048, max(512, $wordCount * 6));

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

WHAT YOU MAY CHANGE vs MUST PRESERVE:
- PRESERVE (never alter): facts, figures, definitions, the pedagogical objective, assessment intent, and the set of concepts taught. You add no new facts, no new topics, no new data, no URLs, and no citations.
- MAY CHANGE: phrasing, order, depth of explanation, scaffolding, emphasis — and, when the ENRICHMENT block below permits it, you MAY add a short illustrative scenario or analogy whose only purpose is to make a concept ALREADY in the original easier to grasp. Enrichment illustrates the existing material; it must not introduce new factual content or extend the scope of what is taught.

STRICT RULES:
1. Every fact, figure, definition, and concept in your output must trace back to the original content. Illustrative scenarios/analogies (when permitted) may use everyday framing but must not assert any new fact about the subject.
2. Do not change the pedagogical objective or assessment intent.
3. Never rewrite, hint at, or paraphrase quiz/assessment questions.
4. Preserve technical terms and definitions exactly as written unless instructor permissions allow simplification of surrounding prose only.
5. If the original already fits the student profile and no enrichment is requested, return it with minimal or no changes.
6. Output markdown body only — no preamble, labels, or meta commentary.
7. For "visual" delivery: reorganize existing sentences into markdown tables or bullet lists — do not invent diagram content.
8. For "example-based" delivery: lead with an example. Prefer one already in the original; if none exists and enrichment is permitted, you may add a short illustrative scenario that demonstrates the existing concept without adding new facts.
9. "Advanced depth" means surface relationships between concepts already stated, and — when enrichment is permitted — anchor them with one or more concise worked scenarios or analogies. Never introduce new concepts.
10. Never repeat scaffolding, context, or term definitions already established in earlier chunks of the same lesson. The LESSON CONTEXT block (when present) tells you what came before — honour it.
11. NARRATIVE OPENING (when the PRESENTATION MODE block requests it): you may open with a brief, relatable hook — a question or mini-scenario ("Have you ever…", "Imagine…", "Picture…") that frames the concept's real-world context before it is explained. The hook is framing only: it asserts no new fact, figure, or definition, and simply sets up material already in the original.
12. SOCRATIC QUESTIONS (when the PRESENTATION MODE / DEPTH block requests it for an advanced learner): you may pose short reflective "why / what-if" questions about a concept just presented, each followed by a one-to-two sentence reasoning walkthrough. These are thinking prompts woven into the prose — they are NOT graded, must never resemble, reveal, or pre-empt a quiz/assessment question, and must reason only over facts already in the original.
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

        $allowSimplificationBool = (bool) ($settings['allow_simplification'] ?? true);
        $allowExampleSubstitutionBool = (bool) ($settings['allow_example_substitution'] ?? true);
        $allowAnalogiesBool = (bool) ($settings['allow_analogies'] ?? true);
        $allowSimplification = $allowSimplificationBool ? 'yes' : 'no';
        $allowExampleSubstitution = $allowExampleSubstitutionBool ? 'yes' : 'no';
        $allowAnalogies = $allowAnalogiesBool ? 'yes' : 'no';
        $lockTechnicalDefinitions = ($settings['lock_technical_definitions'] ?? true) ? 'yes' : 'no';

        // Enrichment is permitted only when the instructor allows illustrative material
        // (analogies or example substitution). It lets advanced learners receive a worked
        // scenario and novices receive concrete scaffolding — always illustrating existing
        // concepts, never adding new facts.
        $enrichmentEnabled = $allowAnalogiesBool || $allowExampleSubstitutionBool;

        $depthInstruction = match ($knowledgeLevel) {
            'advanced' => $enrichmentEnabled
                ? 'Write denser, more sophisticated prose. Surface the relationships between ideas already in the original, then anchor the core concept with UP TO TWO concise worked scenarios or analogies that illustrate it (no new facts). Where it deepens reasoning, pose one or two short Socratic "why / what-if" questions about a concept just covered, each followed by a one-to-two sentence reasoning walkthrough (thinking prompts only — never graded, never resembling a quiz question). Compress obvious repetition.'
                : 'Emphasize connections between ideas already in the original. Compress repetition. Do not add new material.',
            'novice' => $enrichmentEnabled
                ? 'Apply Mayer\'s Signaling for a novice learner: break the explanation into short numbered micro-steps, open each step with a directional cue ("First, …", "Next, …", "Finally, …"), bold the key technical terms on first mention, add brief inline clarifications of terms already defined in the original, and ground the idea with ONE simple everyday analogy that illustrates it (no new facts).'
                : 'Apply Mayer\'s Signaling using only the original wording: short numbered steps, each opening with a directional cue ("First, …", "Next, …"), bold key terms on first mention, and brief inline clarifications of terms already defined in the original.',
            default => $enrichmentEnabled
                ? 'Write balanced, clear prose. Surface a couple of key connections between ideas already in the original, anchor the concept with ONE concise worked example or analogy that illustrates it (no new facts), and pose ONE gentle Socratic "why / what-if" question about a concept just covered, followed by a one-to-two sentence reasoning walkthrough (a thinking prompt only — never graded, never resembling a quiz question).'
                : 'Write balanced, clear prose that surfaces a couple of key connections between ideas already in the original, and pose ONE gentle reflective "why / what-if" question followed by a one-to-two sentence reasoning walkthrough (no new facts, never a quiz question).',
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

        // A narrative opening hook is permitted whenever the instructor allows illustrative
        // material — it is framing, not a level-gated enrichment, so even intermediate
        // narrative learners receive it.
        $narrativeHookAllowed = $enrichmentEnabled;

        // Enrichment (worked scenarios / analogies) needs headroom. Advanced high-performers
        // additionally receive Socratic prompts and a second scenario, so give that path the
        // most room (~240%, ceiling 800); other enrichment paths ~200%; balanced path ~125%.
        $originalWords = str_word_count(strip_tags($originalText));
        $enrichmentForLevel = $enrichmentEnabled && in_array($knowledgeLevel, ['advanced', 'novice'], true);
        $advancedEnriched = $enrichmentEnabled && $knowledgeLevel === 'advanced';
        $intermediateEnriched = $enrichmentEnabled && $knowledgeLevel === 'intermediate';
        if ($advancedEnriched) {
            $budgetMultiplier = 2.4;
            $budgetCeiling = 800;
        } elseif ($enrichmentForLevel) { // novice scaffolding
            $budgetMultiplier = 2.0;
            $budgetCeiling = 650;
        } elseif ($intermediateEnriched) { // worked example + one reflective question
            $budgetMultiplier = 1.7;
            $budgetCeiling = 550;
        } else {
            $budgetMultiplier = 1.25;
            $budgetCeiling = 400;
        }
        $wordBudget = (int) min($budgetCeiling, max(80, (int) ($originalWords * $budgetMultiplier)));

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

        // Build the enrichment directive block. This is the "ENRICHMENT block" the system
        // prompt refers to — it tells the rewriter whether it may add an illustrative
        // scenario/analogy for this learner, and is gated by instructor permissions + level.
        if ($enrichmentForLevel) {
            $enrichmentBlock = <<<'EN'

ENRICHMENT: ENABLED for this learner.
- You MAY add ONE short illustrative scenario or analogy that makes an EXISTING concept easier to grasp.
- The scenario/analogy introduces NO new facts, figures, definitions, or topics — it only illustrates what the original already teaches.
- Keep all preserved facts and technical terms intact. The added illustration is clearly supportive, not a new section of content.
EN;
        } else {
            $enrichmentBlock = <<<'EN'

ENRICHMENT: DISABLED.
- Do not add scenarios or analogies. Rephrase, reorder, and adjust depth using the original material only.
EN;
        }

        // Build optional presentation mode block
        $presentationModeBlock = '';
        $presentationMode = (string) ($lessonContext['presentation_mode'] ?? '');

        // Advanced high-performers in deep_focus get Socratic reflective questions; other
        // learners do not, so the prompt stays focused on dense connective prose for them.
        $deepFocusSocratic = $advancedEnriched
            ? "\n- Where it sharpens reasoning, pose one or two short Socratic \"why / what-if\" questions about a concept just covered, each followed by a one-to-two sentence reasoning walkthrough. These are thinking prompts only — never graded, never resembling a quiz question."
            : '';

        // Narrative delivery opens with a relatable hook when the instructor permits
        // illustrative framing; otherwise it falls back to leading with an existing example.
        $narrativeOpening = $narrativeHookAllowed
            ? "- Open with a brief relatable hook (1–2 sentences) that frames the concept's real-world context — a question or mini-scenario (\"Have you ever…\", \"Imagine…\", \"Picture…\"). The hook adds no new facts; it sets up the concept already in the original.\n- Then place a --- markdown separator, followed by the concept/theory.\n- If the original already contains a worked example, fold it in after the hook (hook → example/theory) rather than discarding it."
            : "- If an example or scenario exists in the original, lead with it before the theoretical explanation.\n- Use a --- markdown separator between the example/scenario and the theory section.";

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
                'deep_focus' => <<<PM

PRESENTATION MODE: deep_focus
- Write in dense academic prose; avoid unnecessary step numbering or bullet lists.
- Surface logical connections between ideas stated in the original using connective language (therefore, consequently, which implies, etc.).
- Compress any redundant phrasing from the original.
- Apply Mayer's Signaling only for the single most critical concept (bold once).{$deepFocusSocratic}
PM,
                'narrative_example' => <<<PM

PRESENTATION MODE: narrative_example
{$narrativeOpening}
- Write in a conversational explanatory tone.
- Ensure the theory section explicitly refers back to the opening hook/example.
PM,
                default => '',
            };
        }

        return <<<TXT
ORIGINAL INSTRUCTOR CONTENT (authoritative — do not alter meaning):
"""
{$originalText}
"""

LEARNER LEVEL SIGNATURE (delivery tuning only — this copy is shared by all learners at this
level, so it depends only on these coarse signals, never on any individual's identity or scores):
- Knowledge level: {$knowledgeLevel}
- Pace: {$pace} — {$paceInstruction}
- Delivery modality: {$modality} — {$modalityInstruction}

DEPTH GUIDANCE:
{$depthInstruction}

INSTRUCTOR PERMISSIONS:
- Simplification: {$allowSimplification}
- Example substitution: {$allowExampleSubstitution}
- Analogies (illustrating original concepts only): {$allowAnalogies}
- Lock technical definitions word-for-word: {$lockTechnicalDefinitions}
- Adaptation depth: {$minDifficulty}–{$maxDifficulty} (1=minimal rephrase, 5=full delivery rewrite; never add facts)
{$enrichmentBlock}{$lessonContextBlock}{$presentationModeBlock}
TASK:
Produce a student delivery copy. Same learning objective. Same facts. Phrasing, structure, scaffolding, and permitted illustrative framing may change.
Target length: about {$wordBudget} words.
TXT;
    }
}
