<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Jobs\RecalculateProfileJob;
use App\Models\Activity;
use App\Models\AdaptationLog;
use App\Models\AdaptationSetting;
use App\Models\ContentChunk;
use App\Models\LessonPage;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AdaptableContentResolver;
use App\Services\ActivityMaterialService;
use App\Services\AdaptationIntegrityService;
use App\Services\ContentChunkingService;
use App\Services\GeminiAdaptationService;
use App\Services\PersonalizationContextService;
use App\Services\PresentationAdaptationService;
use App\Services\StudentProfileService;
use App\Services\VideoTranscriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class AdaptiveContentController extends Controller
{
    /**
     * Version token mixed into every adapted-payload cache key. BUMP THIS whenever the
     * delivery prompts or adaptation logic in GeminiAdaptationService change, so previously
     * cached adaptations are treated as misses and regenerated instead of replayed for 24h.
     */
    private const ADAPTATION_CACHE_VERSION = 'v2-narrative-hook';

    public function __construct(
        private GeminiAdaptationService $geminiService,
        private StudentProfileService $profileService,
        private PersonalizationContextService $contextService,
        private PresentationAdaptationService $presentationService,
        private AdaptationIntegrityService $integrityService,
        private AdaptableContentResolver $contentResolver,
        private ActivityMaterialService $materialService,
        private ContentChunkingService $chunkingService,
        private VideoTranscriptService $videoTranscriptService,
    ) {}

    /**
     * GET /api/student/content-chunks/{contentId}
     */
    public function chunks(string $contentId, Request $request): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $source = $request->query('source', 'lesson_page');
        $chunks = ContentChunk::where('content_id', $contentId)
            ->where('content_source', $source)
            ->orderBy('chunk_index')
            ->get(['id', 'chunk_index', 'chunk_text', 'chunk_type', 'content_source']);

        if ($source === 'lesson_page' && $chunks->isEmpty()) {
            $page = LessonPage::find($contentId);

            if ($page && trim((string) $page->content) !== '') {
                $chunkSource = $this->videoTranscriptService->enrichLessonPageHtml($page->content);

                if (trim(strip_tags($chunkSource)) !== '') {
                    $this->chunkingService->chunkWithSemantics(
                        $page->id,
                        $chunkSource,
                        $page->page_type ?? 'content',
                        'lesson_page',
                    );

                    $chunks = ContentChunk::where('content_id', $contentId)
                        ->where('content_source', $source)
                        ->orderBy('chunk_index')
                        ->get(['id', 'chunk_index', 'chunk_text', 'chunk_type', 'content_source']);

                    // Generate and persist lesson context summary now (deferred from show() to avoid
                    // a second Gemini call per chunk request later)
                    if ($page->ai_context_summary === null || $page->context_summary_at === null) {
                        $plainText = trim(mb_substr(
                            preg_replace('/\s+/', ' ', strip_tags($chunkSource)) ?? '',
                            0,
                            4000
                        ));
                        if ($plainText !== '') {
                            $summary = $this->generateLessonSummary($plainText);
                            if ($summary !== '') {
                                $page->ai_context_summary = $summary;
                                $page->context_summary_at = now();
                                try { $page->save(); } catch (\Throwable) {}
                            }
                        }
                    }
                }
            }
        }

        // Safety net: if chunks exist but summary was never generated (e.g. upgraded from older version),
        // generate it now so show() never needs to call Gemini for lesson context.
        if ($source === 'lesson_page' && ! $chunks->isEmpty()) {
            $pageForCtx = LessonPage::find($contentId);
            if ($pageForCtx && $pageForCtx->ai_context_summary === null) {
                $rawPlain = trim(mb_substr(
                    preg_replace('/\s+/', ' ', strip_tags($pageForCtx->content ?? '')) ?? '',
                    0,
                    4000
                ));
                if ($rawPlain !== '') {
                    $ctxSummary = $this->generateLessonSummary($rawPlain);
                    if ($ctxSummary !== '') {
                        $pageForCtx->ai_context_summary = $ctxSummary;
                        $pageForCtx->context_summary_at = now();
                        try { $pageForCtx->save(); } catch (\Throwable) {}
                    }
                }
            }
        }

        return response()->json([
            'content_id' => $contentId,
            'content_source' => $source,
            'chunks' => $chunks,
        ]);
    }

    /**
     * GET /api/v1/student/activities/{activityId}/adaptive-chunks
     */
    public function activityChunks(string $activityId): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $activity = Activity::find($activityId);
        if (! $activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        $result = $this->contentResolver->chunksForActivity($activity);

        return response()->json([
            'activity_id' => $activityId,
            'activity_type' => $activity->type,
            'status' => $result['status'],
            'material' => $result['material'] ? [
                'id' => $result['material']->id,
                'type' => $result['material']->type,
                'processing_status' => $result['material']->processing_status,
                'processing_error' => $result['material']->processing_error,
                'word_count' => $result['material']->word_count,
                'has_extracted_text' => $result['material']->hasExtractedText(),
            ] : null,
            'chunks' => $result['chunks'],
        ]);
    }

    /**
     * POST /api/v1/student/activities/{activityId}/prepare-adaptation
     * Lazy-sync legacy uploads into course_materials and queue extraction.
     */
    public function prepareActivity(string $activityId): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $activity = Activity::find($activityId);
        if (! $activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        $material = $this->materialService->ensureForActivity($activity);
        $result = $this->contentResolver->chunksForActivity($activity);

        return response()->json([
            'activity_id' => $activityId,
            'status' => $result['status'],
            'material' => $material ? [
                'id' => $material->id,
                'type' => $material->type,
                'processing_status' => $material->processing_status,
                'processing_error' => $material->processing_error,
            ] : null,
            'chunk_count' => $result['chunks']->count(),
            'message' => match ($result['status']) {
                'ready' => 'Adaptable content is ready.',
                'processing', 'pending' => 'Material is being processed for personalization.',
                'transcript_unavailable' => 'Original video remains available. A reliable transcript could not be generated for personalization.',
                'content_mismatch' => 'Original video remains available. Personalization is paused because the extracted content does not appear aligned with this course activity.',
                'no_extractable_text' => 'Original asset preserved. Not enough text to personalize (e.g. video without transcript).',
                default => 'No adaptable material found for this activity.',
            },
        ]);
    }

    /**
     * GET /api/student/content/{chunkId}
     */
    public function show(string $chunkId, Request $request): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $chunk = ContentChunk::find($chunkId);
        if (! $chunk) {
            return response()->json(['message' => 'Chunk not found.'], 404);
        }

        // Never adapt assessments or quizzes
        if (in_array($chunk->chunk_type, ['quiz', 'assessment'], true)) {
            return response()->json($this->deliveryPayload(
                chunk: $chunk,
                displayText: $chunk->chunk_text,
                profile: null,
                presentation: null,
                settings: null,
                deliveryStatus: 'original_only',
                contentAdapted: false,
                presentationActive: false,
                integrity: ['instructor_content_immutable' => true, 'assessment_protected' => true],
                extra: ['assessment_protected' => true],
            ));
        }

        // Fetch course context from lesson page or course material
        $context = $this->contentResolver->courseContextForChunk($chunk);
        $courseId = $context['course_id'];
        $sectionId = $context['section_id'];

        $contentProfile = $this->contextService->contentProfileForAdaptation($student->id, $courseId);
        $profileArray = array_merge($contentProfile, [
            'profile_hash' => md5(json_encode(array_filter([
                'pace' => $contentProfile['pace'] ?? 'medium',
                'quiz_average' => $contentProfile['quiz_average'] ?? 0,
                'weak_topics' => $contentProfile['weak_topics'] ?? [],
                'preferred_modality' => $contentProfile['preferred_modality'] ?? 'text',
                'completion_rate' => $contentProfile['completion_rate'] ?? 0,
                'knowledge_level' => $contentProfile['knowledge_level'] ?? 'intermediate',
                // Include the signals that drive enrichment/mode so a profile change
                // re-triggers adaptation instead of serving a stale cached payload.
                'primary_profile' => $contentProfile['primary_profile'] ?? null,
                'vark_style' => $contentProfile['vark_style'] ?? null,
                'at_risk' => $contentProfile['at_risk'] ?? false,
                'risk_tier' => $contentProfile['risk_tier'] ?? null,
            ]))),
        ]);

        $mode = $this->presentationService->selectMode(array_merge($contentProfile, [
            'student_id' => $student->id,
            'course_id'  => $courseId,
        ]));
        $presentationConfig = $this->presentationService->resolve(
            $contentProfile,
            $student,
            null,
            $mode,
        );

        // Warm-up gate: during the cold-start window serve the instructor's original
        // content unchanged — no AI rewrite and no presentation switch. Keeps the
        // system honest until there is real evidence about the learner on this course.
        if (($contentProfile['personalization_ready'] ?? true) === false) {
            return response()->json($this->deliveryPayload(
                chunk: $chunk,
                displayText: $chunk->chunk_text,
                profile: $profileArray,
                presentation: $presentationConfig,
                settings: null,
                deliveryStatus: 'original_only',
                contentAdapted: false,
                presentationActive: false,
                integrity: ['instructor_content_immutable' => true],
            ));
        }

        // Allow modality override from query param
        if ($request->has('modality_override')) {
            $override = $request->query('modality_override');
            if (in_array($override, ['visual', 'text', 'example-based'], true)) {
                $profileArray['preferred_modality'] = $override;
                // Recompute hash with override
                $sorted = $profileArray;
                unset($sorted['profile_hash']);
                ksort($sorted);
                $profileArray['profile_hash'] = md5(json_encode($sorted));
            }
        }

        $profileHash = $profileArray['profile_hash'];

        // Check if a flagged adaptation exists for this student+chunk
        $flagged = AdaptationLog::where('student_id', $student->id)
            ->where('chunk_id', $chunkId)
            ->where('flagged', true)
            ->exists();

        if ($flagged) {
            return response()->json($this->deliveryPayload(
                chunk: $chunk,
                displayText: $chunk->chunk_text,
                profile: $profileArray,
                presentation: $presentationConfig,
                settings: null,
                deliveryStatus: 'flagged',
                contentAdapted: false,
                presentationActive: (bool) ($presentationConfig['is_active'] ?? false),
                integrity: ['instructor_content_immutable' => true],
                extra: ['flagged_reason' => 'Instructor flagged a previous adaptation for this content'],
            ));
        }

        // Build cache key
        // Version suffix (kept last so the "adapt:{studentId}:*" invalidation glob still
        // matches) ensures a prompt/logic deploy supersedes pre-deploy cached payloads.
        $cacheKey = "adapt:{$student->id}:{$chunkId}:{$profileHash}:" . self::ADAPTATION_CACHE_VERSION;

        // Check file cache for previously adapted payload
        $cachedPayload = $this->fileCacheGet($cacheKey);
        if (is_array($cachedPayload) && isset($cachedPayload['adapted_text'])) {
            return response()->json(array_merge($cachedPayload, [
                'cached' => true,
                'original_text' => $chunk->chunk_text,
                'profile' => $profileArray,
                'presentation' => $presentationConfig,
            ]));
        }

        // Fetch instructor adaptation settings (courseId/sectionId resolved above)

        $settings = AdaptationSetting::where('course_id', $courseId)
            ->where('topic_id', $sectionId)
            ->first();

        if (! $settings) {
            $settings = AdaptationSetting::where('course_id', $courseId)
                ->whereNull('topic_id')
                ->first();
        }

        $settingsArray = $settings ? $settings->toArray() : [
            'allow_simplification' => true,
            'allow_example_substitution' => true,
            'allow_analogies' => true,
            'lock_technical_definitions' => true,
            'prevent_assessment_rewrite' => true,
            'min_difficulty' => 1,
            'max_difficulty' => 5,
            'ai_confidence_threshold' => 0.75,
        ];

        $lessonCtx = $this->buildLessonContext($chunk, $presentationConfig);
        $rawAdapted = $this->geminiService->adapt($chunk->chunk_text, $profileArray, $settingsArray, $lessonCtx);

        if ($rawAdapted === null || $rawAdapted === '') {
            Log::warning('Gemini adaptation failed, returning original text', [
                'student_id' => $student->id,
                'chunk_id' => $chunkId,
                'course_id' => $courseId,
                'section_id' => $sectionId,
                'student_knowledge_level' => $profileArray['knowledge_level'] ?? 'unknown',
                'student_pace' => $profileArray['pace'] ?? 'unknown',
            ]);

            return response()->json($this->deliveryPayload(
                chunk: $chunk,
                displayText: $chunk->chunk_text,
                profile: $profileArray,
                presentation: $presentationConfig,
                settings: $settingsArray,
                deliveryStatus: 'fallback',
                contentAdapted: false,
                presentationActive: (bool) ($presentationConfig['is_active'] ?? false),
                integrity: ['instructor_content_immutable' => true],
                extra: ['fallback_reason' => 'AI adaptation unavailable'],
            ));
        }

        // Advanced high-performers receive extra scenarios + Socratic prompts, which run
        // longer than the default 2.2x cap. Relax the length ceiling for that path only so
        // the richer delivery is accepted rather than rejected back to the original.
        $enrichmentAllowed = ($settingsArray['allow_analogies'] ?? true) || ($settingsArray['allow_example_substitution'] ?? true);
        $advancedEnriched = ($profileArray['knowledge_level'] ?? 'intermediate') === 'advanced' && $enrichmentAllowed;
        $assessSettings = $settingsArray;
        if ($advancedEnriched) {
            $assessSettings['max_length_ratio'] = 2.8;
        }

        $assessment = $this->integrityService->assess($chunk->chunk_text, $rawAdapted, $assessSettings);
        $displayText = $assessment['adapted_text'];
        $contentAdapted = $assessment['content_adapted'];
        $deliveryStatus = $assessment['delivery_status'];

        if (! $contentAdapted && ($presentationConfig['is_active'] ?? false)) {
            $deliveryStatus = 'presentation_only';
        }

        $presentationActive = (bool) ($presentationConfig['is_active'] ?? false);
        $adaptationId = null;

        if ($contentAdapted) {
            $log = AdaptationLog::create([
                'id' => Str::uuid()->toString(),
                'student_id' => $student->id,
                'chunk_id' => $chunkId,
                'adapted_text' => $displayText,
                'original_text' => $chunk->chunk_text,
                'profile_snapshot' => $profileArray,
                'instructor_settings_snapshot' => $settingsArray,
                'flagged' => false,
            ]);
            $adaptationId = $log->id;
        }

        $payload = $this->deliveryPayload(
            chunk: $chunk,
            displayText: $displayText,
            profile: $profileArray,
            presentation: $presentationConfig,
            settings: $settingsArray,
            deliveryStatus: $deliveryStatus,
            contentAdapted: $contentAdapted,
            presentationActive: $presentationActive,
            integrity: $assessment['integrity'],
            extra: [
                'adaptation_id' => $adaptationId,
                'cached' => false,
                'rejection_reason' => $assessment['rejection_reason'],
                'similarity_to_original_percent' => $assessment['similarity_percent'],
            ],
        );

        $this->fileCachePut($cacheKey, $payload, now()->addSeconds(86400));

        return response()->json($payload);
    }

    /**
     * Honest, structured API response — never claim adaptation when only layout changed or AI failed.
     *
     * @param  array<string, mixed>|null  $profile
     * @param  array<string, mixed>|null  $presentation
     * @param  array<string, mixed>|null  $settings
     * @param  array<string, mixed>  $integrity
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function deliveryPayload(
        ContentChunk $chunk,
        string $displayText,
        ?array $profile,
        ?array $presentation,
        ?array $settings,
        string $deliveryStatus,
        bool $contentAdapted,
        bool $presentationActive,
        array $integrity,
        array $extra = [],
    ): array {
        $transparencyMessage = $this->integrityService->transparencyMessage($deliveryStatus, $presentationActive);

        return array_merge([
            'adapted_text' => $displayText,
            'original_text' => $chunk->chunk_text,
            'delivery_status' => $deliveryStatus,
            'content_adapted' => $contentAdapted,
            'presentation_active' => $presentationActive,
            'is_personalized' => $contentAdapted,
            'profile' => $profile,
            'presentation' => $presentation,
            'settings_applied' => $settings,
            'integrity' => $integrity,
            'transparency' => [
                'message' => $transparencyMessage,
                'instructor_content_immutable' => true,
                'layers' => [
                    'content' => $contentAdapted,
                    'presentation' => $presentationActive,
                    'navigation' => false,
                ],
            ],
            'adaptation_id' => null,
            'cached' => false,
        ], $extra);
    }

    /**
     * POST /api/student/adaptation/{adaptationId}/feedback
     */
    public function feedback(string $adaptationId, Request $request): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $log = AdaptationLog::where('id', $adaptationId)
            ->where('student_id', $student->id)
            ->first();

        if (! $log) {
            return response()->json(['message' => 'Adaptation log not found.'], 404);
        }

        $request->validate([
            'rating' => 'nullable|in:positive,negative',
            'complexity' => 'nullable|in:too_simple,just_right,too_complex',
        ]);

        $rating = $request->input('rating');
        $complexity = $request->input('complexity');

        $updateData = [];
        if ($rating) {
            $updateData['feedback_rating'] = $rating;
        }
        if ($complexity) {
            $updateData['feedback_complexity'] = $complexity;
        }

        if (! empty($updateData)) {
            $log->update($updateData);
        }

        // If rating is negative OR complexity is too_simple OR too_complex → recalculate profile
        if ($rating === 'negative' || $complexity === 'too_simple' || $complexity === 'too_complex') {
            RecalculateProfileJob::dispatch($student->id);
        }

        return response()->json(['message' => 'Feedback recorded.']);
    }

    /**
     * POST /api/student/{studentId}/recalculate-profile
     */
    public function recalculateProfile(Request $request, string $studentId): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = Auth::user();
        if (! $currentUser) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Only allow recalculating own profile, or instructors/admins for any student
        if ($currentUser->id !== $studentId && ! $currentUser->isInstructor() && ! $currentUser->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $oldProfile = StudentProfile::where('student_id', $studentId)->first();
        $oldHash = $oldProfile?->profile_hash;

        $profile = $this->profileService->recalculate($studentId);

        // Apply manual modality override if provided
        $manualModality = $request->input('manual_modality');
        if ($manualModality && in_array($manualModality, ['visual', 'text', 'example-based'], true)) {
            $profile['preferred_modality'] = $manualModality;
            $sorted = $profile;
            unset($sorted['profile_hash']);
            ksort($sorted);
            $profile['profile_hash'] = md5(json_encode($sorted));

            StudentProfile::updateOrCreate(
                ['student_id' => $studentId],
                [
                    'preferred_modality' => $manualModality,
                    // Learning style is presentation-only: persist the player/layout the
                    // learner chose so selectMode() honors it above the instructor pin.
                    'preferred_presentation_mode' => PresentationAdaptationService::modeForStyle($manualModality, null),
                    'profile_hash' => $profile['profile_hash'],
                    'updated_at' => now(),
                ]
            );
        }

        // If profile_hash changed, delete Redis keys
        if ($oldHash !== ($profile['profile_hash'] ?? null)) {
            try {
                $redis = Redis::connection();
                $keys = $redis->keys("adapt:{$studentId}:*");
                if (! empty($keys)) {
                    $redis->del($keys);
                }
            } catch (\Throwable $e) {
                Log::warning('Could not delete Redis keys during manual recalculation', [
                    'student_id' => $studentId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Profile recalculated.',
            'profile' => $profile,
        ]);
    }

    /**
     * Call Gemini directly for a ≤200-word lesson summary without using the throwing GeminiService.
     */
    private function generateLessonSummary(string $plainText): string
    {
        $apiKey  = (string) (config('services.gemini.api_key') ?? '');
        $model   = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
        $baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');

        if ($apiKey === '') {
            return '';
        }

        try {
            $response = Http::timeout(25)->withHeaders(['Content-Type' => 'application/json'])->post(
                "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}",
                [
                    'contents' => [['role' => 'user', 'parts' => [['text' => "Summarise the learning objectives and main sections of this lesson in ≤200 words:\n\n{$plainText}"]]]],
                    'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 300],
                    'systemInstruction' => ['parts' => [['text' => 'Summarise educational lesson content concisely. Return plain text only.']]],
                ]
            );

            if ($response->successful()) {
                return trim((string) $response->json('candidates.0.content.parts.0.text', ''));
            }
        } catch (\Throwable $e) {
            Log::warning('AdaptiveContentController: lesson summary generation failed', ['error' => $e->getMessage()]);
        }

        return '';
    }

    /**
     * Build lesson-level context for the chunk adaptation call.
     * Generates and caches a ≤200-word lesson summary on first access.
     *
     * @param  array<string, mixed>  $presentationConfig
     * @return array<string, mixed>
     */
    private function buildLessonContext(ContentChunk $chunk, array $presentationConfig = []): array
    {
        if ($chunk->content_source !== 'lesson_page') {
            return [];
        }

        $page = LessonPage::find($chunk->content_id);
        if (! $page) {
            return [];
        }

        // Summary is generated in chunks() on first load and persisted to DB — just read it here.
        // Collect key_terms from prior chunks to prevent re-introduction
        $priorTerms = ContentChunk::where('content_id', $chunk->content_id)
            ->where('content_source', 'lesson_page')
            ->where('chunk_index', '<', $chunk->chunk_index)
            ->whereNotNull('key_terms')
            ->get(['key_terms'])
            ->flatMap(fn ($c) => $c->key_terms ?? [])
            ->unique()
            ->values()
            ->toArray();

        $totalChunks = ContentChunk::where('content_id', $chunk->content_id)
            ->where('content_source', 'lesson_page')
            ->count();

        return [
            'lesson_summary'    => (string) ($page->ai_context_summary ?? ''),
            'semantic_role'     => (string) ($chunk->semantic_role ?? ''),
            'key_terms'         => $priorTerms,
            'position_pct'      => (int) ($chunk->lesson_position_pct ?? 0),
            'chunk_index'       => (int) ($chunk->chunk_index ?? 0),
            'total_chunks'      => $totalChunks,
            'presentation_mode' => (string) ($presentationConfig['mode'] ?? ''),
        ];
    }

    /**
     * GET /api/v1/student/activities/{activityId}/video-learning-support
     * Returns personalised transcript, summary, notes, and study questions for a video activity.
     */
    public function videoLearningSupport(string $activityId): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $activity = Activity::find($activityId);
        if (! $activity) {
            return response()->json(['message' => 'Activity not found.'], 404);
        }

        // Resolve course context
        $courseId = null;
        if ($activity->section_id) {
            $section = \Illuminate\Support\Facades\DB::table('sections')
                ->where('id', $activity->section_id)
                ->value('course_id');
            $courseId = $section ? (string) $section : null;
        }

        $contentProfile = $this->contextService->contentProfileForAdaptation($student->id, $courseId);
        $profileHash = md5(json_encode([
            $contentProfile['knowledge_level'] ?? '',
            $contentProfile['preferred_modality'] ?? '',
            $contentProfile['weak_topics'] ?? [],
        ]));
        $cacheKey = "video-support:{$activityId}:{$student->id}:{$profileHash}:" . self::ADAPTATION_CACHE_VERSION;

        // Return cached response when available
        $cached = $this->fileCacheGet($cacheKey);
        if (is_array($cached)) {
            return response()->json(array_merge($cached, ['cached' => true]));
        }

        // Resolve transcript
        $transcript = '';
        $hasTranscript = false;

        $videoUrl = (string) ($activity->video_url ?? $activity->url ?? '');

        if ($videoUrl !== '') {
            // YouTube URL
            $transcript = $this->videoTranscriptService->transcribeYouTube($videoUrl);
            $hasTranscript = trim($transcript) !== '';
        }

        if (! $hasTranscript) {
            // Local video via course material
            $result = $this->contentResolver->chunksForActivity($activity);
            if ($result['material'] && $result['material']->hasExtractedText()) {
                $transcript = (string) ($result['material']->extracted_text ?? '');
                $hasTranscript = trim($transcript) !== '';
            }
        }

        if (! $hasTranscript) {
            return response()->json([
                'activity_id'     => $activityId,
                'has_transcript'  => false,
                'transcript'      => '',
                'summary'         => '',
                'notes'           => ['key_points' => [], 'definitions' => [], 'study_questions' => [], 'further_review' => []],
                'profile_applied' => ['knowledge_level' => $contentProfile['knowledge_level'] ?? 'intermediate', 'modality' => $contentProfile['preferred_modality'] ?? 'text'],
            ]);
        }

        $summary = $this->videoTranscriptService->summarizeForLearner($transcript, $contentProfile);
        $notes   = $this->videoTranscriptService->extractLearnerNotes($transcript, $contentProfile);

        $payload = [
            'activity_id'     => $activityId,
            'has_transcript'  => true,
            'transcript'      => mb_substr($transcript, 0, 6000),
            'summary'         => $summary,
            'notes'           => $notes,
            'profile_applied' => [
                'knowledge_level' => $contentProfile['knowledge_level'] ?? 'intermediate',
                'modality'        => $contentProfile['preferred_modality'] ?? 'text',
            ],
            'cached' => false,
        ];

        $this->fileCachePut($cacheKey, $payload, now()->addHours(12));

        return response()->json($payload);
    }

    private function fileCacheGet(string $key): mixed
    {
        try {
            return Cache::store('file')->get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fileCachePut(string $key, mixed $value, \DateTimeInterface $ttl): void
    {
        try {
            Cache::store('file')->put($key, $value, $ttl);
        } catch (\Throwable) {}
    }

    /**
     * GET /api/student/my-profile
     */
    public function myProfile(): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $profile = StudentProfile::where('student_id', $student->id)->first();

        $adaptationCountThisWeek = AdaptationLog::where('student_id', $student->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $feedbackSummary = AdaptationLog::where('student_id', $student->id)
            ->whereNotNull('feedback_rating')
            ->selectRaw('feedback_rating, COUNT(*) as count')
            ->groupBy('feedback_rating')
            ->pluck('count', 'feedback_rating')
            ->toArray();

        return response()->json([
            'profile' => $profile?->toArray(),
            'adaptation_count_this_week' => $adaptationCountThisWeek,
            'feedback_summary' => $feedbackSummary,
        ]);
    }
}
