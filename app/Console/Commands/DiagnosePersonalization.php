<?php

namespace App\Console\Commands;

use App\Http\Controllers\Student\AdaptiveContentController;
use App\Models\AdaptationLog;
use App\Models\AdaptationSetting;
use App\Models\ContentChunk;
use App\Models\Course;
use App\Models\User;
use App\Services\AdaptationIntegrityService;
use App\Services\GeminiAdaptationService;
use App\Services\PersonalizationContextService;
use App\Services\PresentationAdaptationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Runs the REAL content-adaptation pipeline for one student on one course and reports, at each
 * decision point, whether the learner gets personalized content — and if not, exactly why.
 * Built to diagnose production ("still the same content") without guessing: stale code (OPcache),
 * exhausted Gemini quota, the warm-up gate, integrity rejection, or a missing API key.
 */
class DiagnosePersonalization extends Command
{
    protected $signature   = 'personalization:diagnose {email} {course=DEMO-AI}';
    protected $description = 'Diagnose why a student is or is not receiving adapted content for a course.';

    public function handle(
        PersonalizationContextService $context,
        PresentationAdaptationService $presentation,
        GeminiAdaptationService $gemini,
        AdaptationIntegrityService $integrity,
    ): int {
        $email = (string) $this->argument('email');
        $courseRef = (string) $this->argument('course');

        $user = User::where('email', $email)->first();
        if (! $user) {
            $this->error("No user with email {$email}");

            return self::FAILURE;
        }

        $course = Course::where('short_name', $courseRef)->orWhere('id', $courseRef)->first();
        if (! $course) {
            $this->error("No course matching {$courseRef} (short_name or id)");

            return self::FAILURE;
        }

        // ── Environment ────────────────────────────────────────────────────
        $this->line('');
        $this->info('── Environment ──');
        $key = (string) (config('services.gemini.api_key') ?? '');
        $this->line('Gemini API key: '.($key !== '' ? 'set (len '.strlen($key).')' : 'MISSING'));
        $this->line('Gemini model:   '.(string) config('services.gemini.model'));
        $this->line('Adapt cooldown (recent 429): '.($gemini->isCoolingDown() ? 'ACTIVE — quota/rate-limited' : 'off'));
        $this->line('Deployed cache version: '.$this->cacheVersion().'  (expected: v4-shared)');
        $this->line('AdaptationLog rows (all-time successes): '.AdaptationLog::count());

        // ── Gemini connectivity probe ──────────────────────────────────────
        // Two minimal raw calls reveal the EXACT API status/body that adapt() swallows. Running
        // both (without/with thinkingConfig) isolates a key/project failure from a thinkingConfig
        // incompatibility.
        $this->line('');
        $this->info('── Gemini connectivity probe ──');
        $p1 = $this->probeGemini($key, false);
        $this->line('probe (no thinkingConfig): HTTP '.$p1['status'].'  '.$p1['body']);
        $p2 = $this->probeGemini($key, true);
        $this->line('probe (thinkingBudget=0) : HTTP '.$p2['status'].'  '.$p2['body']);
        $this->line('VERDICT: '.$this->interpretProbe($p1, $p2));

        // ── Profile ────────────────────────────────────────────────────────
        $profile = $context->contentProfileForAdaptation($user->id, $course->id);
        $this->line('');
        $this->info("── Profile: {$email} on {$course->short_name} ──");
        foreach (['knowledge_level', 'quiz_average', 'pace', 'preferred_modality', 'preferred_presentation_mode', 'personalization_ready', 'at_risk'] as $k) {
            $this->line(str_pad($k, 28).': '.var_export($profile[$k] ?? null, true));
        }

        $mode = $presentation->selectMode(array_merge($profile, [
            'student_id' => $user->id,
            'course_id'  => $course->id,
        ]));
        $this->line(str_pad('selected presentation mode', 28).': '.$mode);

        if (($profile['personalization_ready'] ?? true) === false) {
            $this->warn('VERDICT: warm-up gate — student lacks tenure/quiz evidence on this course; original is served until ready.');

            return self::SUCCESS;
        }

        // ── Per-chunk adaptation ───────────────────────────────────────────
        $settings = $this->settingsFor($course->id);
        $chunks = $this->chunksForCourse($course->id);
        if ($chunks->isEmpty()) {
            $this->warn("No content chunks found for {$course->short_name}. The lesson may not be chunked for adaptation.");

            return self::SUCCESS;
        }

        $maxRatio = ($profile['knowledge_level'] ?? '') === 'advanced' ? ['max_length_ratio' => 2.8] : [];

        foreach ($chunks as $chunk) {
            $this->line('');
            $this->info("── Chunk {$chunk->chunk_index} ({$chunk->semantic_role}) — original ".strlen($chunk->chunk_text).' chars ──');

            if ($gemini->isCoolingDown()) {
                $this->warn('VERDICT: Gemini rate-limited (cooldown) — original served. Quota exhausted; wait for reset or raise limits.');
                break;
            }

            $out = $gemini->adapt($chunk->chunk_text, $profile, $settings, ['presentation_mode' => $mode]);
            if ($out === null || $out === '') {
                $this->error('adapt() returned NULL — Gemini call failed (see laravel.log; usually quota/429, network, or missing key). Original served.');
                continue;
            }

            $assessment = $integrity->assess($chunk->chunk_text, $out, array_merge($settings, $maxRatio));
            $this->line('adapt output length : '.strlen($out));
            $this->line('delivery_status     : '.$assessment['delivery_status']);
            $this->line('rejection_reason    : '.var_export($assessment['rejection_reason'], true));
            $this->line('similarity_percent  : '.$assessment['similarity_percent'].'%');
            $this->line('preview             : '.mb_substr(preg_replace('/\s+/', ' ', $out), 0, 200));

            if ($assessment['delivery_status'] === 'adapted') {
                $this->info('VERDICT: WORKING — this learner receives personalized content for this chunk.');
            } elseif ($assessment['rejection_reason'] === 'length_out_of_bounds' && strlen($out) < 120) {
                $this->error('VERDICT: output truncated (~tiny) — old code still running. Reload PHP-FPM to flush OPcache (thinking-token fix not active).');
            } else {
                $this->warn('VERDICT: not adapted ('.$assessment['rejection_reason'].') — original served for this chunk.');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Minimal raw generateContent call. Returns the real HTTP status + a body snippet so the
     * exact Google error is visible (adapt() swallows it and returns null).
     *
     * @return array{status: int|string, body: string}
     */
    private function probeGemini(string $key, bool $withThinking): array
    {
        if ($key === '') {
            return ['status' => 'n/a', 'body' => 'GEMINI_API_KEY not set'];
        }

        $model = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
        $baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = "{$baseUrl}/models/{$model}:generateContent?key={$key}";

        $generationConfig = ['maxOutputTokens' => 1];
        if ($withThinking) {
            $generationConfig['thinkingConfig'] = ['thinkingBudget' => 0];
        }

        try {
            $resp = Http::timeout(20)->withHeaders(['Content-Type' => 'application/json'])->post($url, [
                'contents' => [['role' => 'user', 'parts' => [['text' => 'ping']]]],
                'generationConfig' => $generationConfig,
            ]);

            return [
                'status' => $resp->status(),
                'body' => mb_substr(preg_replace('/\s+/', ' ', $resp->body()) ?? '', 0, 400),
            ];
        } catch (\Throwable $e) {
            return ['status' => 'EXC', 'body' => get_class($e).': '.$e->getMessage()];
        }
    }

    /**
     * @param  array{status: int|string, body: string}  $p1
     * @param  array{status: int|string, body: string}  $p2
     */
    private function interpretProbe(array $p1, array $p2): string
    {
        $s1 = $p1['status'];
        $s2 = $p2['status'];

        if ($s1 === 200 && $s2 === 200) {
            return 'Gemini key/project OK. If chunks still fail, investigate payload size or transient errors.';
        }
        if ($s1 === 200 && $s2 !== 200) {
            return "thinkingConfig is rejected in this environment (probe #2 failed) — model/endpoint does not support thinkingBudget. Remove it or change model.";
        }

        $blob = strtolower($p1['body'].' '.$p2['body']);
        if (str_contains($blob, 'api_key_invalid') || str_contains($blob, 'api key not valid')) {
            return 'GEMINI_API_KEY is invalid/expired in prod .env — replace it.';
        }
        if (str_contains($blob, 'permission_denied') || str_contains($blob, 'has not been used') || str_contains($blob, 'is disabled') || str_contains($blob, 'service_disabled')) {
            return 'Generative Language API is not enabled for this key\'s Google Cloud project, OR the key has referrer/IP restrictions blocking this server. Enable the API / remove key restrictions.';
        }
        if (str_contains($blob, 'not found') || $s1 === 404 || $s2 === 404) {
            return 'Model not found — fix GEMINI_MODEL in prod .env (current: '.(string) config('services.gemini.model').').';
        }
        if ($s1 === 429 || $s2 === 429) {
            return 'Quota/rate limit (429) — wait for reset or enable billing.';
        }

        return 'Unexpected Gemini error — see the status/body above.';
    }

    private function cacheVersion(): string
    {
        try {
            $ref = new \ReflectionClass(AdaptiveContentController::class);

            return (string) $ref->getConstant('ADAPTATION_CACHE_VERSION');
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /** @return array<string, mixed> */
    private function settingsFor(string $courseId): array
    {
        $settings = AdaptationSetting::where('course_id', $courseId)->whereNull('topic_id')->first();

        return $settings ? $settings->toArray() : [
            'allow_simplification' => true,
            'allow_example_substitution' => true,
            'allow_analogies' => true,
            'lock_technical_definitions' => true,
            'prevent_assessment_rewrite' => true,
            'min_difficulty' => 1,
            'max_difficulty' => 5,
            'ai_confidence_threshold' => 0.75,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, ContentChunk> */
    private function chunksForCourse(string $courseId)
    {
        $activityIds = DB::table('activities')->where('course_id', $courseId)->pluck('id');
        $pageIds = DB::table('lesson_pages')->whereIn('activity_id', $activityIds)->pluck('id');

        return ContentChunk::whereIn('content_id', $pageIds)
            ->whereNotIn('chunk_type', ['quiz', 'assessment'])
            ->orderBy('chunk_index')
            ->get();
    }
}
