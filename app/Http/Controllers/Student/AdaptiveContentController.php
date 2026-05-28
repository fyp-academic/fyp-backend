<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Jobs\RecalculateProfileJob;
use App\Models\AdaptationLog;
use App\Models\AdaptationSetting;
use App\Models\ContentChunk;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\GeminiAdaptationService;
use App\Services\StudentProfileService;
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
    ) {}

    /**
     * GET /api/student/content-chunks/{contentId}
     */
    public function chunks(string $contentId): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $chunks = ContentChunk::where('content_id', $contentId)
            ->orderBy('chunk_index')
            ->get(['id', 'chunk_index', 'chunk_text', 'chunk_type']);

        return response()->json([
            'content_id' => $contentId,
            'chunks' => $chunks,
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
            return response()->json([
                'adapted_text' => $chunk->chunk_text,
                'adaptation_id' => null,
                'cached' => false,
                'is_personalized' => false,
                'original_text' => $chunk->chunk_text,
                'profile' => null,
                'settings_applied' => null,
            ]);
        }

        // Fetch or recalculate student profile
        $studentProfile = StudentProfile::where('student_id', $student->id)->first();
        if (! $studentProfile) {
            $profileArray = $this->profileService->recalculate($student->id);
        } else {
            $profileArray = [
                'pace' => $studentProfile->pace,
                'quiz_average' => $studentProfile->quiz_average,
                'weak_topics' => $studentProfile->weak_topics ?? [],
                'preferred_modality' => $studentProfile->preferred_modality,
                'completion_rate' => $studentProfile->completion_rate,
                'profile_hash' => $studentProfile->profile_hash,
            ];
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
            return response()->json([
                'adapted_text' => $chunk->chunk_text,
                'adaptation_id' => null,
                'cached' => false,
                'is_personalized' => false,
                'original_text' => $chunk->chunk_text,
                'profile' => $profileArray,
                'settings_applied' => $settingsArray ?? null,
                'flagged_reason' => 'Instructor flagged this adaptation',
            ]);
        }

        // Build cache key
        $cacheKey = "adapt:{$student->id}:{$chunkId}:{$profileHash}";

        // Check Redis cache
        $cached = Cache::store('redis')->get($cacheKey);
        if ($cached) {
            return response()->json([
                'adapted_text' => $cached,
                'adaptation_id' => null,
                'cached' => true,
                'is_personalized' => true,
                'original_text' => $chunk->chunk_text,
                'profile' => $profileArray,
                'settings_applied' => $settingsArray ?? null,
            ]);
        }

        // Fetch instructor adaptation settings
        // Determine course from the lesson page -> activity -> section -> course chain
        $lessonPage = $chunk->lessonPage;
        $courseId = null;
        $sectionId = null;
        if ($lessonPage && $lessonPage->activity && $lessonPage->activity->section) {
            $courseId = $lessonPage->activity->course_id;
            $sectionId = $lessonPage->activity->section_id;
        }

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

        // Call Gemini
        $adaptedText = $this->geminiService->adapt($chunk->chunk_text, $profileArray, $settingsArray);

        if ($adaptedText === null || $adaptedText === '') {
            // Fallback silently
            Log::warning('Gemini adaptation failed, returning original text', [
                'student_id' => $student->id,
                'chunk_id' => $chunkId,
            ]);

            return response()->json([
                'adapted_text' => $chunk->chunk_text,
                'adaptation_id' => null,
                'cached' => false,
                'is_personalized' => false,
                'original_text' => $chunk->chunk_text,
                'profile' => $profileArray,
                'settings_applied' => $settingsArray ?? null,
                'fallback_reason' => 'Gemini adaptation failed',
            ]);
        }

        // Store in Redis
        try {
            Cache::store('redis')->put($cacheKey, $adaptedText, now()->addSeconds(86400));
        } catch (\Throwable $e) {
            Log::warning('Redis cache store failed', ['error' => $e->getMessage()]);
        }

        // Log to adaptation_log
        $log = AdaptationLog::create([
            'id' => Str::uuid()->toString(),
            'student_id' => $student->id,
            'chunk_id' => $chunkId,
            'adapted_text' => $adaptedText,
            'original_text' => $chunk->chunk_text,
            'profile_snapshot' => $profileArray,
            'instructor_settings_snapshot' => $settingsArray,
            'flagged' => false,
        ]);

        return response()->json([
            'adapted_text' => $adaptedText,
            'adaptation_id' => $log->id,
            'cached' => false,
            'is_personalized' => true,
            'original_text' => $chunk->chunk_text,
            'profile' => $profileArray,
            'settings_applied' => $settingsArray,
        ]);
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
