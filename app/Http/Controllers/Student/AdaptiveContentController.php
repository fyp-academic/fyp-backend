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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class AdaptiveContentController extends Controller
{
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
                    $this->chunkingService->chunk(
                        $page->id,
                        $chunkSource,
                        $page->page_type ?? 'content',
                        'lesson_page',
                    );

                    $chunks = ContentChunk::where('content_id', $contentId)
                        ->where('content_source', $source)
                        ->orderBy('chunk_index')
                        ->get(['id', 'chunk_index', 'chunk_text', 'chunk_type', 'content_source']);
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
            ] : null,
            'chunk_count' => $result['chunks']->count(),
            'message' => match ($result['status']) {
                'ready' => 'Adaptable content is ready.',
                'processing', 'pending' => 'Material is being processed for personalization.',
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
            ]))),
        ]);

        $presentationConfig = $this->presentationService->resolve(
            $contentProfile,
            $student,
            null,
        );

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
        $cacheKey = "adapt:{$student->id}:{$chunkId}:{$profileHash}";

        // Check Redis cache (gracefully degrade if Redis is unavailable)
        $cachedPayload = null;
        try {
            $cachedPayload = Cache::store('redis')->get($cacheKey);
        } catch (\Throwable $e) {
            Log::warning('Redis cache get failed', ['error' => $e->getMessage()]);
        }
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

        $rawAdapted = $this->geminiService->adapt($chunk->chunk_text, $profileArray, $settingsArray);

        if ($rawAdapted === null || $rawAdapted === '') {
            Log::warning('Gemini adaptation failed, returning original text', [
                'student_id' => $student->id,
                'chunk_id' => $chunkId,
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

        $assessment = $this->integrityService->assess($chunk->chunk_text, $rawAdapted, $settingsArray);
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

        try {
            Cache::store('redis')->put($cacheKey, $payload, now()->addSeconds(86400));
        } catch (\Throwable $e) {
            Log::warning('Redis cache store failed', ['error' => $e->getMessage()]);
        }

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
