<?php

namespace App\Http\Controllers;

use App\Models\AdaptationLog;
use App\Models\AdaptationSetting;
use App\Models\ContentChunk;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class InstructorAdaptationController extends Controller
{
    /**
     * GET /api/instructor/settings/{courseId}/{topicId}
     */
    public function getSettings(string $courseId, string $topicId): JsonResponse
    {
        $settings = AdaptationSetting::where('course_id', $courseId)
            ->where('topic_id', $topicId)
            ->first();

        if (! $settings) {
            $settings = AdaptationSetting::firstOrCreate(
                ['course_id' => $courseId, 'topic_id' => $topicId],
                [
                    'id' => Str::uuid()->toString(),
                    'allow_simplification' => true,
                    'allow_example_substitution' => true,
                    'allow_analogies' => true,
                    'lock_technical_definitions' => true,
                    'prevent_assessment_rewrite' => true,
                    'min_difficulty' => 1,
                    'max_difficulty' => 5,
                    'ai_confidence_threshold' => 0.75,
                ]
            );
        }

        return response()->json(['data' => $settings]);
    }

    /**
     * PUT /api/instructor/settings/{courseId}/{topicId}
     */
    public function updateSettings(string $courseId, string $topicId, Request $request): JsonResponse
    {
        $request->validate([
            'allow_simplification' => 'sometimes|boolean',
            'allow_example_substitution' => 'sometimes|boolean',
            'allow_analogies' => 'sometimes|boolean',
            'lock_technical_definitions' => 'sometimes|boolean',
            'prevent_assessment_rewrite' => 'sometimes|boolean',
            'min_difficulty' => 'sometimes|integer|min:1|max:5',
            'max_difficulty' => 'sometimes|integer|min:1|max:5',
            'ai_confidence_threshold' => 'sometimes|numeric|min:0.5|max:1.0',
        ]);

        $settings = AdaptationSetting::updateOrCreate(
            ['course_id' => $courseId, 'topic_id' => $topicId],
            array_merge(
                $request->only([
                    'allow_simplification',
                    'allow_example_substitution',
                    'allow_analogies',
                    'lock_technical_definitions',
                    'prevent_assessment_rewrite',
                    'min_difficulty',
                    'max_difficulty',
                    'ai_confidence_threshold',
                ]),
                [
                    'id' => Str::uuid()->toString(),
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]
            )
        );

        // Clear cached adaptations for chunks belonging to this course
        try {
            $chunkIds = ContentChunk::whereHas('lessonPage.activity.section', function ($query) use ($courseId) {
                $query->where('course_id', $courseId);
            })->pluck('id')->toArray();

            if (! empty($chunkIds)) {
                $redis = Redis::connection();
                foreach ($chunkIds as $chunkId) {
                    $keys = $redis->keys("adapt:*:{$chunkId}:*");
                    if (! empty($keys)) {
                        $redis->del($keys);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Could not clear course adaptation cache', [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Settings updated.', 'data' => $settings]);
    }

    /**
     * GET /api/instructor/adaptations
     */
    public function auditLog(Request $request): JsonResponse
    {
        $query = AdaptationLog::with(['student', 'chunk.lessonPage.activity.section'])
            ->orderByDesc('created_at');

        if ($request->has('course_id')) {
            $courseId = $request->query('course_id');
            $query->whereHas('chunk.lessonPage.activity.section', function ($q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        if ($request->has('topic_id')) {
            $topicId = $request->query('topic_id');
            $query->whereHas('chunk.lessonPage.activity.section', function ($q) use ($topicId) {
                $q->where('id', $topicId);
            });
        }

        if ($request->boolean('flagged')) {
            $query->where('flagged', true);
        }

        if ($request->has('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        $perPage = $request->query('per_page', 20);
        $results = $query->paginate($perPage);

        $data = $results->through(function (AdaptationLog $log) {
            $studentName = $log->student?->name ?? 'Unknown';
            $topicName = $log->chunk?->lessonPage?->activity?->section?->title ?? 'Unknown';

            $feedbackLabel = null;
            if ($log->feedback_rating) {
                $feedbackLabel = ($log->feedback_rating === 'positive' ? '👍 ' : '👎 ')
                    . ($log->feedback_complexity ? ucfirst(str_replace('_', ' ', $log->feedback_complexity)) : '');
            }

            return [
                'id' => $log->id,
                'student_id' => $log->student_id,
                'student_name' => $studentName,
                'topic_name' => $topicName,
                'created_at' => $log->created_at->toDateTimeString(),
                'feedback_rating' => $log->feedback_rating,
                'feedback_complexity' => $log->feedback_complexity,
                'feedback_label' => $feedbackLabel,
                'flagged' => $log->flagged,
                'original_text' => $log->original_text,
                'adapted_text' => $log->adapted_text,
            ];
        });

        return response()->json($data);
    }

    /**
     * POST /api/instructor/adaptations/{adaptationId}/flag
     */
    public function flag(string $adaptationId): JsonResponse
    {
        $instructor = Auth::user();
        if (! $instructor) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $log = AdaptationLog::find($adaptationId);
        if (! $log) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $log->update([
            'flagged' => true,
            'flagged_by' => $instructor->id,
        ]);

        // Delete Redis key for this student+chunk
        try {
            $redis = Redis::connection();
            $keys = $redis->keys("adapt:{$log->student_id}:{$log->chunk_id}:*");
            if (! empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not delete Redis keys during flag', [
                'student_id' => $log->student_id,
                'chunk_id' => $log->chunk_id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Flagged.', 'flagged' => true]);
    }

    /**
     * POST /api/instructor/adaptations/{adaptationId}/unflag
     */
    public function unflag(string $adaptationId): JsonResponse
    {
        $log = AdaptationLog::find($adaptationId);
        if (! $log) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $log->update([
            'flagged' => false,
            'flagged_by' => null,
        ]);

        return response()->json(['message' => 'Unflagged.', 'flagged' => false]);
    }

    /**
     * GET /api/instructor/students/{studentId}/profile
     */
    public function studentProfile(string $studentId): JsonResponse
    {
        $student = User::find($studentId);
        if (! $student) {
            return response()->json(['message' => 'Student not found.'], 404);
        }

        $profile = StudentProfile::where('student_id', $studentId)->first();

        $adaptationCountThisWeek = AdaptationLog::where('student_id', $studentId)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $feedbackSummary = AdaptationLog::where('student_id', $studentId)
            ->whereNotNull('feedback_rating')
            ->selectRaw('feedback_rating, COUNT(*) as count')
            ->groupBy('feedback_rating')
            ->pluck('count', 'feedback_rating')
            ->toArray();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'profile' => $profile?->toArray(),
            'adaptation_count_this_week' => $adaptationCountThisWeek,
            'feedback_summary' => $feedbackSummary,
        ]);
    }
}
