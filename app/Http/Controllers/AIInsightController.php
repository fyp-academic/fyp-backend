<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\GeminiService;
use App\Models\Course;
use App\Models\AiAtRiskStudent;
use App\Models\AiRecommendation;
use App\Models\AiContentRecommendation;
use App\Models\AiGeneratedQuestion;
use App\Models\ActivityPerformance;
use App\Models\DashboardEngagement;
use App\Models\EngagementScore;
use App\Models\QuizAttempt;
use App\Models\Section;
use App\Models\Activity;

class AIInsightController extends Controller
{
    /**
     * GET /api/v1/courses/{id}/ai/performance
     * Weekly KPI trends — LIVE-computed from measured data (no demo seed).
     *
     * Per-week engagement & completion come from the real EngagementScore
     * history (one bucket per week_number, averaged across learners). The
     * grade line is the cumulative class average of graded quiz attempts up to
     * the end of each week — a truthful trajectory rather than a guess.
     *
     * Returns `[{ period, avg_grade, completion_rate, engagement_score }]`.
     * Empty array when no engagement has been measured yet.
     */
    public function performance(string $id): JsonResponse
    {
        Course::findOrFail($id);

        $scores = EngagementScore::where('course_id', $id)
            ->orderBy('week_number')
            ->get(['week_number', 'engagement_score', 'content_completion_score', 'computed_at']);

        if ($scores->isEmpty()) {
            return response()->json(['data' => [], 'course_id' => $id]);
        }

        // One bucket per week_number: average across learners, keep the latest
        // computed_at as the week's anchor for aligning grade data.
        $weeks = $scores->groupBy('week_number')->map(function ($rows, $weekNumber) {
            $engagement = $rows->whereNotNull('engagement_score')->avg('engagement_score');
            $completion = $rows->whereNotNull('content_completion_score')->avg('content_completion_score');
            return [
                'week_number' => (int) $weekNumber,
                'engagement'  => $engagement !== null ? round($engagement, 1) : 0.0,
                'completion'  => $completion !== null ? round($completion, 1) : 0.0,
                'anchor'      => $rows->max('computed_at'),
            ];
        })->sortBy('week_number')->values();

        // Keep the trend readable — last 10 measured weeks.
        $weeks = $weeks->slice(-10)->values();

        // Graded attempts, oldest first, for cumulative grade-to-date per week.
        $attempts = QuizAttempt::where('course_id', $id)
            ->whereNotNull('score')
            ->where('max_score', '>', 0)
            ->whereNotNull('submitted_at')
            ->orderBy('submitted_at')
            ->get(['score', 'max_score', 'submitted_at']);

        $series = $weeks->map(function ($w) use ($attempts) {
            $upTo = $attempts;
            if ($w['anchor']) {
                $upTo = $attempts->filter(fn ($a) => $a->submitted_at !== null && $a->submitted_at->lte($w['anchor']));
            }
            $avgGrade = $upTo->isNotEmpty()
                ? round($upTo->avg(fn ($a) => ($a->score * 1.0 / $a->max_score) * 100), 1)
                : 0.0;

            return [
                'period'           => 'W' . $w['week_number'],
                'avg_grade'        => $avgGrade,
                'completion_rate'  => $w['completion'],
                'engagement_score' => $w['engagement'],
            ];
        })->values();

        return response()->json(['data' => $series, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/courses/{id}/ai/skills
     * Skill/topic mastery radar — LIVE-computed class-average quiz score (%) per
     * section (topic). Only sections with at least one graded attempt appear, so
     * the chart never invents mastery for un-assessed topics.
     *
     * Returns `[{ metric, score }]`.
     */
    public function skills(string $id): JsonResponse
    {
        Course::findOrFail($id);

        $sections = Section::where('course_id', $id)
            ->orderBy('sort_order')
            ->get(['id', 'title']);

        $data = [];
        foreach ($sections as $section) {
            $quizActivityIds = Activity::where('section_id', $section->id)
                ->where('type', 'quiz')
                ->pluck('id');

            if ($quizActivityIds->isEmpty()) {
                continue;
            }

            $attempts = QuizAttempt::whereIn('activity_id', $quizActivityIds)
                ->whereNotNull('score')
                ->where('max_score', '>', 0)
                ->get(['score', 'max_score']);

            if ($attempts->isEmpty()) {
                continue;
            }

            $data[] = [
                'metric' => $section->title,
                'score'  => round($attempts->avg(fn ($a) => ($a->score * 1.0 / $a->max_score) * 100), 1),
            ];
        }

        return response()->json(['data' => $data, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/courses/{id}/ai/at-risk
     * GPT-flagged at-risk students with risk level, missed activities, grade, and recommendation.
     */
    public function atRisk(string $id): JsonResponse
    {
        Course::findOrFail($id);

        $data = AiAtRiskStudent::where('course_id', $id)
            ->orderBy('risk_level', 'desc')
            ->get();

        return response()->json(['data' => $data, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/courses/{id}/ai/recommendations[?refresh=1]
     * Actionable pedagogical suggestions, AI-generated from MEASURED course data.
     * Cached in ai_recommendations; reused while fresh (<24h) unless refresh=1.
     * Returns [] when there is not enough measured data to ground a suggestion.
     */
    public function recommendations(Request $request, string $id): JsonResponse
    {
        Course::findOrFail($id);
        $refresh = $request->boolean('refresh');

        if (! $refresh) {
            $fresh = AiRecommendation::where('course_id', $id)
                ->where('generated_at', '>=', now()->subDay())
                ->orderByDesc('generated_at')
                ->get();
            if ($fresh->isNotEmpty()) {
                return response()->json(['data' => $fresh, 'course_id' => $id]);
            }
        }

        $measures = $this->gatherCourseMeasures($id);
        if (! $measures['has_data']) {
            return response()->json(['data' => [], 'course_id' => $id]);
        }

        $items = [];
        try {
            $items = app(GeminiService::class)
                ->generateInstructorSuggestions($measures['summary'], $measures['course_name']);
        } catch (\Throwable $e) {
            Log::warning('AIInsight suggestions generation failed', ['error' => $e->getMessage()]);
        }

        // On a transient failure keep whatever we already have rather than wiping.
        if (empty($items)) {
            return response()->json([
                'data' => AiRecommendation::where('course_id', $id)->orderByDesc('generated_at')->get(),
                'course_id' => $id,
            ]);
        }

        AiRecommendation::where('course_id', $id)->delete();
        $saved = collect();
        foreach (array_slice($items, 0, 5) as $it) {
            if (empty($it['title'])) {
                continue;
            }
            $saved->push(AiRecommendation::create([
                'id'           => Str::uuid()->toString(),
                'course_id'    => $id,
                'title'        => (string) $it['title'],
                'description'  => (string) ($it['description'] ?? ''),
                'impact_level' => in_array($it['impact_level'] ?? '', ['low', 'medium', 'high'], true)
                    ? $it['impact_level'] : 'medium',
                'generated_at' => now(),
            ]));
        }

        return response()->json(['data' => $saved, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/courses/{id}/ai/content[?refresh=1]
     * Resource recommendations AI-generated from MEASURED performance gaps.
     * Cached in ai_content_recommendations; reused while fresh (<24h) unless refresh=1.
     * Returns [] when there is not enough measured data.
     */
    public function contentRecommendations(Request $request, string $id): JsonResponse
    {
        Course::findOrFail($id);
        $refresh = $request->boolean('refresh');

        if (! $refresh) {
            $fresh = AiContentRecommendation::where('course_id', $id)
                ->where('generated_at', '>=', now()->subDay())
                ->orderByDesc('relevance_score')
                ->get();
            if ($fresh->isNotEmpty()) {
                return response()->json(['data' => $fresh, 'course_id' => $id]);
            }
        }

        $measures = $this->gatherCourseMeasures($id);
        if (! $measures['has_data']) {
            return response()->json(['data' => [], 'course_id' => $id]);
        }

        $items = [];
        try {
            $items = app(GeminiService::class)
                ->generateInstructorContentRecommendations($measures['summary'], $measures['course_name']);
        } catch (\Throwable $e) {
            Log::warning('AIInsight content recommendation generation failed', ['error' => $e->getMessage()]);
        }

        if (empty($items)) {
            return response()->json([
                'data' => AiContentRecommendation::where('course_id', $id)->orderByDesc('relevance_score')->get(),
                'course_id' => $id,
            ]);
        }

        AiContentRecommendation::where('course_id', $id)->delete();
        $saved = collect();
        foreach (array_slice($items, 0, 6) as $it) {
            if (empty($it['title'])) {
                continue;
            }
            // relevance_score column is decimal(4,2) — clamp to its 0–99.99 range.
            $relevance = min(99.99, max(0, (float) ($it['relevance_score'] ?? 0)));
            $saved->push(AiContentRecommendation::create([
                'id'              => Str::uuid()->toString(),
                'course_id'       => $id,
                'title'           => (string) $it['title'],
                'content_type'    => (string) ($it['content_type'] ?? 'article'),
                'relevance_score' => $relevance,
                'source'          => (string) ($it['source'] ?? ''),
                'url'             => (string) ($it['url'] ?? ''),
                'generated_at'    => now(),
            ]));
        }

        return response()->json(['data' => $saved, 'course_id' => $id]);
    }

    /**
     * Build a compact, MEASURED performance summary for a course to ground the
     * AI generators. `has_data` is false when nothing has been measured yet, so
     * callers can return an honest empty state instead of inventing insights.
     *
     * @return array{has_data: bool, summary: string, course_name: string}
     */
    private function gatherCourseMeasures(string $id): array
    {
        $courseName = (string) (Course::where('id', $id)->value('name') ?? 'Course');

        $attempts = QuizAttempt::where('course_id', $id)
            ->whereNotNull('score')->where('max_score', '>', 0)
            ->get(['score', 'max_score']);

        $scores = EngagementScore::where('course_id', $id)
            ->get(['engagement_score', 'content_completion_score', 'week_number']);

        if ($attempts->isEmpty() && $scores->isEmpty()) {
            return ['has_data' => false, 'summary' => '', 'course_name' => $courseName];
        }

        $avgGrade = $attempts->isNotEmpty()
            ? round($attempts->avg(fn ($a) => ($a->score * 1.0 / $a->max_score) * 100), 1)
            : null;

        $latestWeek = $scores->max('week_number');
        $latest     = $scores->where('week_number', $latestWeek);
        $avgEng     = $latest->whereNotNull('engagement_score')->avg('engagement_score');
        $avgComp    = $latest->whereNotNull('content_completion_score')->avg('content_completion_score');
        $atRisk     = $latest->filter(fn ($s) => ($s->engagement_score ?? 100) < 40)->count();

        // Weak topics: sections whose graded-quiz average is below 60%.
        $weak = [];
        foreach (Section::where('course_id', $id)->orderBy('sort_order')->get(['id', 'title']) as $sec) {
            $quizIds = Activity::where('section_id', $sec->id)->where('type', 'quiz')->pluck('id');
            if ($quizIds->isEmpty()) {
                continue;
            }
            $secAttempts = QuizAttempt::whereIn('activity_id', $quizIds)
                ->whereNotNull('score')->where('max_score', '>', 0)
                ->get(['score', 'max_score']);
            if ($secAttempts->isEmpty()) {
                continue;
            }
            $pct = round($secAttempts->avg(fn ($a) => ($a->score * 1.0 / $a->max_score) * 100), 1);
            if ($pct < 60) {
                $weak[] = "{$sec->title} ({$pct}%)";
            }
        }

        $lines = [];
        $lines[] = 'Average grade: ' . ($avgGrade !== null ? "{$avgGrade}%" : 'no graded attempts yet');
        if ($avgComp !== null) {
            $lines[] = 'Average content completion: ' . round($avgComp) . '%';
        }
        if ($avgEng !== null) {
            $lines[] = 'Average engagement: ' . round($avgEng) . '/100';
        }
        $lines[] = 'Learners currently at risk (engagement < 40): ' . $atRisk;
        $lines[] = $weak
            ? 'Weakest topics: ' . implode('; ', $weak)
            : 'No topic is below 60% — consider stretch/enrichment material.';

        return ['has_data' => true, 'summary' => implode("\n", $lines), 'course_name' => $courseName];
    }

    /**
     * POST /api/v1/courses/{id}/ai/generate-questions
     * Trigger GPT to generate quiz questions for a given topic and difficulty level.
     */
    public function generateQuestions(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'topic'       => 'required|string',
            'difficulty'  => 'required|string|in:easy,medium,hard',
            'count'       => 'sometimes|integer|min:1|max:20',
            'type'        => 'sometimes|string|in:multiple_choice,true_false,short_answer',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Course::findOrFail($id);

        $generated = AiGeneratedQuestion::create([
            'id'            => Str::uuid()->toString(),
            'course_id'     => $id,
            'topic'         => $request->topic,
            'question_text' => '',
            'question_type' => $request->input('type', 'multiple_choice'),
            'difficulty'    => $request->difficulty,
            'status'        => 'generating',
            'generated_at'  => now(),
        ]);

        return response()->json([
            'message'   => 'Question generation queued.',
            'course_id' => $id,
            'data'      => $generated,
        ]);
    }

    /**
     * GET /api/v1/courses/{id}/ai/generated-questions
     * List all AI-generated questions filtered by status.
     */
    public function generatedQuestions(Request $request, string $id): JsonResponse
    {
        Course::findOrFail($id);

        $query  = AiGeneratedQuestion::where('course_id', $id);
        $status = $request->query('status');

        if ($status) {
            $query->where('status', $status);
        }

        $data = $query->orderBy('generated_at', 'desc')->get();

        return response()->json(['data' => $data, 'course_id' => $id, 'status_filter' => $status]);
    }

    /**
     * PATCH /api/v1/ai/generated-questions/{id}
     * Accept (add_to_bank) or dismiss a GPT-generated question.
     */
    public function updateQuestionStatus(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:added_to_bank,dismissed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $question = AiGeneratedQuestion::findOrFail($id);
        $question->update(['status' => $request->status]);

        return response()->json(['message' => 'Question status updated.', 'data' => $question]);
    }

    /**
     * GET /api/v1/courses/{id}/ai/activity-performance
     * Average score percentage per activity (horizontal bar chart data).
     */
    public function activityPerformance(string $id): JsonResponse
    {
        Course::findOrFail($id);

        $data = ActivityPerformance::where('course_id', $id)
            ->orderBy('avg_score_percentage', 'desc')
            ->get();

        return response()->json(['data' => $data, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/courses/{id}/ai/engagement
     * Daily active students and submission counts for the engagement area chart.
     */
    public function engagement(string $id): JsonResponse
    {
        Course::findOrFail($id);

        $data = DashboardEngagement::where('course_id', $id)
            ->orderBy('week_of', 'desc')
            ->orderBy('day_label')
            ->get();

        return response()->json(['data' => $data, 'course_id' => $id]);
    }
}
