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
