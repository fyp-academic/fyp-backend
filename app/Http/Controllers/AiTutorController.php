<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\Activity;
use App\Models\AiAtRiskStudent;
use App\Models\AiContentRecommendation;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Enrollment;
use App\Models\LearnerProfile;
use App\Models\LessonPage;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\GeminiService;

/**
 * AI Tutor Widget Controller
 *
 * Handles all student-facing AI widget interactions.
 * Each endpoint adapts responses based on student's TMS, risk level, and learning style.
 *
 * Modes:   study | restricted | remediation | revision | reflection | general
 * Intents: tutor | summarize | resource | quiz | flashcard | debug | motivate | general
 */
class AiTutorController extends Controller
{
    /** Per-student AI generation rate limit per minute */
    private const RATE_LIMIT_PER_MINUTE = 30;

    /** Max content chars sent to Gemini (token budget control) */
    private const MAX_CONTENT_CHARS = 6000;

    public function __construct(private GeminiService $gemini) {}

    // ─── Rate Limiting & Usage Tracking ──────────────────────────────────

    private function checkRateLimit(string $studentId): ?JsonResponse
    {
        $key   = "ai_tutor_rate:{$studentId}";
        $count = (int) Cache::get($key, 0);

        if ($count >= self::RATE_LIMIT_PER_MINUTE) {
            // Calculate actual remaining seconds until the 60-second window resets
            $ttl = Cache::getRedis()->ttl($key);
            $remaining = max(5, (int) $ttl);

            return response()->json([
                'response' => "You're asking questions faster than I can think! Please wait {$remaining} seconds.",
                'meta'     => ['error' => 'rate_limited', 'retry_after_seconds' => $remaining],
            ], 429);
        }

        // Set window start on first request and expire after 60 seconds
        if ($count === 0) {
            Cache::put($key, 1, now()->addSeconds(60));
        } else {
            Cache::increment($key);
        }
        return null;
    }

    private function checkExternalRateLimit(): ?JsonResponse
    {
        // Circuit breaker: if external AI recently rate-limited, block for 90 seconds
        $key = 'ai_tutor_global_rate_limited';
        if (Cache::has($key)) {
            $ttl = Cache::getRedis()->ttl($key);
            $remaining = max(5, (int) $ttl);
            return response()->json([
                'response' => "The AI service is still cooling down. Please wait {$remaining} seconds and try again.",
                'meta'     => ['error' => 'ai_rate_limited', 'retry_after_seconds' => $remaining],
            ], 429);
        }
        return null;
    }

    private function incrementUsage(string $studentId): void
    {
        $dailyKey = "ai_tutor_daily:{$studentId}:" . now()->format('Y-m-d');
        Cache::increment($dailyKey);
        Cache::put($dailyKey, (int) Cache::get($dailyKey, 0), now()->addHours(25));
    }

    private function getDailyUsage(string $studentId): int
    {
        return (int) Cache::get("ai_tutor_daily:{$studentId}:" . now()->format('Y-m-d'), 0);
    }

    // ─── Standardised Response Builders ──────────────────────────────────

    private function successResponse(string $response, string $mode, string $intent, array $profile): JsonResponse
    {
        return response()->json([
            'response' => $response,
            'meta'     => [
                'status' => 'ok',
                'mode'   => $mode,
                'intent' => $intent,
                'tms'    => $profile['tms'],
            ],
        ]);
    }

    private function handleAiError(\Exception $e, string $endpoint): JsonResponse
    {
        Log::error("AI Tutor [{$endpoint}] failed", [
            'error'   => $e->getMessage(),
            'trace'   => substr($e->getTraceAsString(), 0, 500),
        ]);

        if (str_contains($e->getMessage(), 'Rate limit')) {
            // Set circuit breaker so all students get blocked briefly
            Cache::put('ai_tutor_global_rate_limited', true, now()->addSeconds(90));

            return response()->json([
                'response' => 'The AI service is experiencing high demand. Please wait 90 seconds and try again.',
                'meta'     => ['error' => 'ai_rate_limited', 'retry_after_seconds' => 90],
            ], 429);
        }

        if (str_contains($e->getMessage(), 'Could not connect')) {
            return response()->json([
                'response' => 'Could not reach the AI service. Please check your connection and try again.',
                'meta'     => ['error' => 'connection_failed'],
            ], 503);
        }

        return response()->json([
            'response' => 'Something went wrong on my end. Please try again in a moment.',
            'meta'     => ['error' => 'internal_error'],
        ], 500);
    }

    // ─── Course Context Resolution ───────────────────────────────────────

    private function resolveCourseContext(?string $courseId, ?string $topicId): array
    {
        $courseName = 'your course';

        if ($courseId) {
            $course = Course::find($courseId);
            if ($course) $courseName = $course->name;
        } elseif ($topicId) {
            $activity = Activity::with('course')->find($topicId);
            if ($activity?->course) {
                $courseName = $activity->course->name;
                $courseId    = $activity->course_id;
            }
        }

        return [$courseName, $courseId];
    }

    // ═══════════════════════════════════════════════════════════════
    //  GET /ai/widget-context
    //  Returns greeting, mode, and chips based on current page context
    // ═══════════════════════════════════════════════════════════════

    public function widgetContext(Request $request): JsonResponse
    {
        $student       = $request->user();
        $page          = $request->query('page', '/');
        $courseId       = $request->query('course_id');
        $topicId       = $request->query('topic_id');
        $quizAttemptId = $request->query('quiz_attempt_id');

        $pageLower = strtolower($page);
        $firstName = explode(' ', $student->name)[0] ?? $student->name;

        // Default fallback
        $mode     = 'general';
        $greeting = "👋 Hey {$firstName}! How can I help you learn today?";
        $chips    = [
            ['type' => 'tutor',    'label' => '📊 How am I progressing?'],
            ['type' => 'tutor',    'label' => '📅 Suggest a study plan'],
            ['type' => 'motivate', 'label' => '⚡ I need motivation'],
            ['type' => 'resource', 'label' => '🎬 Find learning resources'],
        ];

        // ── Mode detection priority chain (most specific → general) ──

        // 1. RESTRICTED — active quiz attempt
        if ($quizAttemptId || str_contains($pageLower, '/quiz-active') || str_contains($pageLower, '/quiz-attempt')) {
            $mode     = 'restricted';
            $greeting = '🔒 Quiz in progress — I can clarify terms but won\'t give answers.';
            $chips    = [
                ['type' => 'tutor',    'label' => '❓ Clarify a term in this question'],
                ['type' => 'tutor',    'label' => '🧠 Test-taking strategies'],
                ['type' => 'motivate', 'label' => '💪 I\'m feeling anxious about this'],
            ];
        }
        // 2. REMEDIATION — post-quiz review
        elseif (str_contains($pageLower, '/quiz-review') || str_contains($pageLower, '/quiz-result')) {
            $mode = 'remediation';

            // Try to fetch latest quiz score for personalized greeting
            $quizScore = $this->getLatestQuizScore($student->id, $topicId);
            $greeting  = $quizScore !== null
                ? "📊 You scored {$quizScore}% — let's strengthen what you missed."
                : '📊 Let\'s review your quiz and turn mistakes into mastery.';

            $chips = [
                ['type' => 'tutor',     'label' => '🔍 Explain what I got wrong'],
                ['type' => 'flashcard', 'label' => '🗂️ Make flashcards from mistakes'],
                ['type' => 'resource',  'label' => '🎬 Find videos on weak areas'],
                ['type' => 'quiz',      'label' => '🔄 Practice similar questions'],
            ];
        }
        // 3. REVISION — exam prep / practice pages
        elseif (str_contains($pageLower, '/revision') || str_contains($pageLower, '/exam-prep') || str_contains($pageLower, '/practice')) {
            $mode     = 'revision';
            $greeting = '🎯 Exam prep mode — focused practice and spaced review.';
            $chips    = [
                ['type' => 'quiz',      'label' => '📝 Generate practice questions'],
                ['type' => 'flashcard', 'label' => '🗂️ Create revision flashcards'],
                ['type' => 'summarize', 'label' => '📋 Key concepts cheat sheet'],
                ['type' => 'tutor',     'label' => '🧠 Explain a tricky concept'],
            ];
        }
        // 4. REFLECTION — progress / analytics pages
        elseif (str_contains($pageLower, '/progress') || str_contains($pageLower, '/analytics') || str_contains($pageLower, '/reflection')) {
            $mode     = 'reflection';
            $greeting = '🪞 Let\'s reflect on your learning journey so far.';
            $chips    = [
                ['type' => 'tutor',    'label' => '📊 Summarize my progress'],
                ['type' => 'tutor',    'label' => '🎯 What should I focus on next?'],
                ['type' => 'motivate', 'label' => '💬 I\'m feeling overwhelmed'],
            ];
        }
        // 5. STUDY — lesson, course content, activities
        elseif (str_contains($pageLower, '/lesson') || str_contains($pageLower, '/courses/') || str_contains($pageLower, '/activities') || str_contains($pageLower, '/sections')) {
            $mode = 'study';

            // Enrich greeting with course/topic context
            if ($topicId) {
                $activity = Activity::find($topicId);
                if ($activity) {
                    $greeting = "� {$activity->name} — let's master this!";
                }
            } elseif ($courseId) {
                $course = Course::find($courseId);
                if ($course) {
                    $greeting = "� {$course->name} — let's dive in!";
                }
            } else {
                $greeting = "📚 Study mode — I\'m ready to help you learn.";
            }

            $chips = [
                ['type' => 'summarize', 'label' => '📋 Summarize this topic'],
                ['type' => 'tutor',     'label' => '🧠 Explain this concept'],
                ['type' => 'flashcard', 'label' => '🗂️ Make flashcards'],
                ['type' => 'resource',  'label' => '🎬 Find related videos'],
                ['type' => 'debug',     'label' => '🐛 Help me debug code'],
            ];
        }
        // 6. FORUM
        elseif (str_contains($pageLower, '/forum')) {
            $greeting = '� Forum mode — I\'ll help you craft clear, compelling posts.';
            $chips    = [
                ['type' => 'tutor',     'label' => '✍️ Help me structure my post'],
                ['type' => 'tutor',     'label' => '❓ Clarify my question first'],
                ['type' => 'summarize', 'label' => '📋 Summarize this discussion'],
            ];
        }
        // 7. DASHBOARD
        elseif ($pageLower === '/' || str_contains($pageLower, '/dashboard')) {
            $greeting = "� Welcome back, {$firstName}! Here's your learning pulse.";
            $chips    = [
                ['type' => 'tutor',    'label' => '📊 How am I progressing?'],
                ['type' => 'tutor',    'label' => '📅 Suggest a study plan'],
                ['type' => 'motivate', 'label' => '⚠️ Am I at risk in any course?'],
                ['type' => 'resource', 'label' => '🎬 Find a tutorial video'],
            ];
        }

        return response()->json([
            'greeting' => $greeting,
            'mode'     => $mode,
            'chips'    => $chips,
            'student'  => [
                'name'          => $firstName,
                'today_queries' => $this->getDailyUsage($student->id),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /ai/ask
    //  Main ask endpoint — tutor, quiz practice, restricted, general
    // ═══════════════════════════════════════════════════════════════

    public function ask(Request $request): JsonResponse
    {
        $request->validate([
            'question'        => 'required|string|max:2000',
            'topic_id'        => 'nullable|string',
            'course_id'       => 'nullable|string',
            'quiz_attempt_id' => 'nullable|string',
            'mode'            => 'nullable|string|in:study,restricted,remediation,revision,reflection,general',
            'intent'          => 'nullable|string|in:tutor,summarize,resource,quiz,flashcard,debug,motivate,general',
        ]);

        $student  = $request->user();
        $question = trim($request->input('question'));
        $mode     = $request->input('mode', 'general');
        $intent   = $request->input('intent', 'general');
        $courseId  = $request->input('course_id');
        $topicId  = $request->input('topic_id');

        // Rate limiting
        if ($rateLimited = $this->checkRateLimit($student->id)) {
            return $rateLimited;
        }
        if ($extLimited = $this->checkExternalRateLimit()) {
            return $extLimited;
        }

        // Resolve course context
        [$courseName, $courseId] = $this->resolveCourseContext($courseId, $topicId);

        // Build adaptive student profile
        $profile = $this->buildStudentProfile($student->id, $courseId);

        // ── System-aware query check: answer from DB when possible ──
        $systemAnswer = $this->trySystemQuery($question, $student, $courseId);
        if ($systemAnswer !== null) {
            $this->incrementUsage($student->id);
            return $this->successResponse($systemAnswer, $mode, 'system', $profile);
        }

        try {
            $response = match (true) {
                // Mode-based routing (highest priority)
                $mode === 'restricted'
                    => $this->gemini->restrictedResponse($question, $courseName),

                // Intent-based routing
                $intent === 'quiz'
                    => $this->handleQuizIntent($question, $courseName, $topicId, $profile),

                $intent === 'summarize'
                    => $this->handleInlineSummarize($question, $courseName, $topicId, $profile),

                // Default: Socratic tutoring
                default
                    => $this->gemini->tutorExplain($question, $courseName, $intent, $profile),
            };

            $this->incrementUsage($student->id);

            return $this->successResponse($response, $mode, $intent, $profile);

        } catch (\Exception $e) {
            return $this->handleAiError($e, 'ask');
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    //  PRIVATE — System-Aware Query Handler
    //  Detects questions about real LMS data and answers from the database
    // ═══════════════════════════════════════════════════════════════════════

    private function trySystemQuery(string $question, $student, ?string $activeCourseId = null): ?string
    {
        $q = strtolower($question);

        // ── "How many courses" / "my courses" / "courses available" ──
        if (preg_match('/how many course|my course|courses? (available|enrolled|registered|do i have|am i)/i', $q)) {
            $enrollments = Enrollment::where('user_id', $student->id)
                ->with('course:id,name,short_name')
                ->get();

            if ($enrollments->isEmpty()) {
                return "You are not currently enrolled in any courses. Visit the **Course Catalog** to browse and enroll.";
            }

            $list = $enrollments->map(function ($e) {
                $name     = $e->course->name ?? 'Unknown';
                $code     = $e->course->short_name ?? '';
                $progress = $e->progress ? round($e->progress, 1) . '%' : '0%';
                return "- **{$name}** ({$code}) — {$progress} complete";
            })->implode("\n");

            return "You are enrolled in **{$enrollments->count()}** course(s):\n\n{$list}";
        }

        // ── "My instructors" / "who teaches" / "instructors available" ──
        if (preg_match('/instructor|who teach|lecturer|professor|my teacher/i', $q)) {
            $courseIds = Enrollment::where('user_id', $student->id)->pluck('course_id');

            if ($courseIds->isEmpty()) {
                return "You're not enrolled in any courses yet, so I can't show your instructors. Enroll in a course first!";
            }

            $courses = Course::whereIn('id', $courseIds)
                ->with('instructor:id,name,email')
                ->get();

            $lines = $courses->map(function ($c) {
                $inst = $c->instructor;
                $name = $inst ? $inst->name : 'Not assigned';
                return "- **{$c->name}** ({$c->short_name}) — taught by **{$name}**";
            })->implode("\n");

            return "Here are the instructors for your enrolled courses:\n\n{$lines}";
        }

        // ── "My grades" / "my score" / "how am I doing" ──
        if (preg_match('/my grade|my score|my mark|my result|how am i doing|my progress|my gpa/i', $q)) {
            $enrollments = Enrollment::where('user_id', $student->id)
                ->with('course:id,name,short_name')
                ->get();

            if ($enrollments->isEmpty()) {
                return "You are not enrolled in any courses yet.";
            }

            $lines = $enrollments->map(function ($e) {
                $name     = $e->course->name ?? 'Unknown';
                $progress = $e->progress ? round($e->progress, 1) . '%' : 'N/A';
                return "- **{$name}** — progress: **{$progress}**";
            })->implode("\n");

            return "Here's your current progress across courses:\n\n{$lines}\n\nFor detailed grade breakdowns, check the **Course Progress** page.";
        }

        // ── Course content / topics / lessons / sections ──
        if (preg_match('/what (content|topics?|lessons?|sections?|modules?|is in)|course (content|outline|syllabus)|available in|contents? (of|in|for)|show me .*(content|topics|sections)/i', $q)) {
            $course = $this->resolveCourseMentionedInQuery($q, $student->id, $activeCourseId);

            if (!$course) {
                return "I couldn't determine which course you mean. Try saying the course name or code (e.g. 'What's in CS 101?'), or select a course on the Lessons page first.";
            }

            $course->load('sections.activities');
            $sections = $course->sections ?? collect();

            if ($sections->isEmpty()) {
                return "**{$course->name}** ({$course->short_name}) doesn't have any sections set up yet.";
            }

            $lines = $sections->map(function ($sec) {
                $acts = $sec->activities ?? collect();
                $actNames = $acts->take(5)->pluck('name')->implode(', ');
                $extra    = $acts->count() > 5 ? ' …and ' . ($acts->count() - 5) . ' more' : '';
                return "- **{$sec->title}** ({$acts->count()} activities): {$actNames}{$extra}";
            })->implode("\n");

            return "**{$course->name}** ({$course->short_name}) has **{$sections->count()}** section(s):\n\n{$lines}\n\nGo to **Lessons** → select **{$course->short_name}** to explore each section.";
        }

        // ── At-risk / struggling / falling behind ──
        if (preg_match('/at risk|at-risk|falling behind|struggling|am i failing|will i fail|danger|behind in|weak|poor performance/i', $q)) {
            $enrollments = Enrollment::where('user_id', $student->id)
                ->with('course:id,name,short_name')
                ->get();

            if ($enrollments->isEmpty()) {
                return "You are not enrolled in any courses yet.";
            }

            $courseIds = $enrollments->pluck('course_id');
            $risks = AiAtRiskStudent::where('student_id', $student->id)
                ->whereIn('course_id', $courseIds)
                ->get()
                ->keyBy('course_id');

            $lines = $enrollments->map(function ($e) use ($risks) {
                $name     = $e->course->name ?? 'Unknown';
                $code     = $e->course->short_name ?? '';
                $progress = $e->progress ? round($e->progress, 1) . '%' : 'N/A';
                $risk     = $risks->get($e->course_id);

                if ($risk) {
                    $level = ucfirst($risk->risk_level ?? 'unknown');
                    $emoji = match (strtolower($risk->risk_level ?? '')) {
                        'high'   => '🔴',
                        'medium' => '🟡',
                        'low'    => '🟢',
                        default  => '⚪',
                    };
                    $rec = $risk->ai_recommendation ? "\n  → *{$risk->ai_recommendation}*" : '';
                    return "- {$emoji} **{$name}** ({$code}) — Risk: **{$level}** | Progress: {$progress}{$rec}";
                }

                return "- 🟢 **{$name}** ({$code}) — **No risk detected** | Progress: {$progress}";
            })->implode("\n");

            $highRiskCount = $risks->filter(fn ($r) => strtolower($r->risk_level ?? '') === 'high')->count();

            $summary = $highRiskCount > 0
                ? "⚠️ You are flagged as **at risk** in **{$highRiskCount}** course(s). Here's the breakdown:"
                : "✅ You are **not currently flagged as at risk** in any course. Here's your status:";

            return "{$summary}\n\n{$lines}\n\nGo to **Course Progress** for a detailed view.";
        }

        // ── Quiz scores / quiz results / quiz history ──
        if (preg_match('/quiz (score|result|grade|mark|history|performance)|my quiz|how did i do.*(quiz|test)/i', $q)) {
            $attempts = QuizAttempt::where('student_id', $student->id)
                ->whereNotNull('score')
                ->with('activity:id,name', 'course:id,name,short_name')
                ->orderByDesc('submitted_at')
                ->take(10)
                ->get();

            if ($attempts->isEmpty()) {
                return "You haven't completed any quizzes yet. Head to the **Quizzes** page to start one!";
            }

            $lines = $attempts->map(function ($a) {
                $quizName   = $a->activity->name ?? 'Quiz';
                $courseCode  = $a->course->short_name ?? '';
                $score      = round($a->score, 1);
                $max        = $a->max_score ? round($a->max_score, 1) : '?';
                $pct        = $a->max_score ? round(($a->score / $a->max_score) * 100) . '%' : '';
                $date       = $a->submitted_at ? $a->submitted_at->format('M j') : '';
                return "- **{$quizName}** ({$courseCode}) — **{$score}/{$max}** {$pct} ({$date})";
            })->implode("\n");

            $avg = round($attempts->avg('score'), 1);
            return "Here are your recent quiz results (avg: **{$avg}**):\n\n{$lines}";
        }

        return null; // Not a system query — fall through to Gemini
    }

    /**
     * Resolve which course the student is asking about.
     * Priority: course name/code mentioned in text → active courseId → null.
     */
    private function resolveCourseMentionedInQuery(string $query, string $studentId, ?string $activeCourseId): ?Course
    {
        // Get all enrolled courses
        $enrollments = Enrollment::where('user_id', $studentId)
            ->with('course:id,name,short_name')
            ->get();

        if ($enrollments->isEmpty()) {
            return null;
        }

        // Try to match a course name or short_name mentioned in the query
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course;
            if (!$course) continue;

            $name = strtolower($course->name ?? '');
            $code = strtolower($course->short_name ?? '');

            // Match course code like "CS 101", "cs101", "CS101"
            $codeNormalized  = preg_replace('/\s+/', '', $code);   // "cs101"
            $queryNormalized = preg_replace('/\s+/', '', $query);  // normalize spaces in query too

            if (
                ($code && str_contains($query, $code)) ||
                ($codeNormalized && str_contains($queryNormalized, $codeNormalized)) ||
                ($name && str_contains($query, $name))
            ) {
                return $course;
            }
        }

        // Fallback: use active course from widget context
        if ($activeCourseId) {
            $match = $enrollments->first(fn ($e) => $e->course_id === $activeCourseId);
            if ($match?->course) {
                return $match->course;
            }
        }

        // Last resort: if student has exactly 1 course, use that
        if ($enrollments->count() === 1 && $enrollments->first()->course) {
            return $enrollments->first()->course;
        }

        return null;
    }

    private function handleQuizIntent(string $question, string $courseName, ?string $topicId, array $profile): string
    {
        $content = $this->getTopicContent($topicId);

        $prompt = $content
            ? "Generate ONE practice question for a student in {$courseName}.\n\nTopic content:\n{$content}\n\nStudent request: {$question}"
            : "Generate ONE practice question about {$courseName}.\n\nStudent request: {$question}";

        return $this->gemini->tutorExplain($prompt, $courseName, 'quiz', $profile);
    }

    private function handleInlineSummarize(string $question, string $courseName, ?string $topicId, array $profile): string
    {
        $content = $this->getTopicContent($topicId);

        if (empty($content)) {
            return "I don't have enough content loaded for this topic to generate a summary. "
                 . "Try opening a specific lesson first, or ask me to explain a concept directly.";
        }

        return $this->gemini->summarizeMaterial('Topic content', 'lesson', $content, $profile);
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /ai/summarize
    //  Summarize lesson/material content
    // ═══════════════════════════════════════════════════════════════

    public function summarize(Request $request): JsonResponse
    {
        $request->validate([
            'topic_id'    => 'nullable|string',
            'course_id'   => 'nullable|string',
            'material_id' => 'nullable|string',
            'query'       => 'required|string|max:2000',
        ]);

        $student    = $request->user();
        $topicId    = $request->input('topic_id');
        $courseId   = $request->input('course_id');
        $materialId = $request->input('material_id');

        if ($rateLimited = $this->checkRateLimit($student->id)) {
            return $rateLimited;
        }
        if ($extLimited = $this->checkExternalRateLimit()) {
            return $extLimited;
        }

        // ── Content resolution cascade ───────────────────────────────
        $resolved = $this->resolveContentForSummary($materialId, $topicId);

        if (empty($resolved['content'])) {
            return response()->json([
                'response'  => "I couldn't find content to summarize for this topic yet. "
                             . "Try navigating to a specific lesson or ensure the material has been processed.",
                'source'    => null,
                'resources' => [],
                'meta'      => ['status' => 'no_content'],
            ]);
        }

        // ── Generate adaptive summary ────────────────────────────────
        $courseId = $courseId ?: Activity::find($topicId)?->course_id;
        $profile  = $this->buildStudentProfile($student->id, $courseId);

        try {
            $summary   = $this->gemini->summarizeMaterial(
                $resolved['title'],
                $resolved['type'],
                $resolved['content'],
                $profile
            );
            $resources = $this->getRelatedResources($courseId, $topicId);

            $this->incrementUsage($student->id);

            return response()->json([
                'response'  => $summary,
                'source'    => $resolved['source'],
                'resources' => $resources,
                'meta'      => [
                    'status'       => 'ok',
                    'content_type' => $resolved['type'],
                    'word_count'   => str_word_count($resolved['content']),
                    'tms_used'     => $profile['tms'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleAiError($e, 'summarize');
        }
    }

    /**
     * Resolve content to summarize — priority: material → lesson pages → activity materials.
     */
    private function resolveContentForSummary(?string $materialId, ?string $topicId): array
    {
        $empty = ['title' => '', 'type' => '', 'content' => '', 'source' => null];

        // Explicit material ID
        if ($materialId) {
            $material = CourseMaterial::find($materialId);
            if ($material && $material->hasExtractedText()) {
                return [
                    'title'   => $material->title,
                    'type'    => $material->type,
                    'content' => $material->extracted_text,
                    'source'  => ['title' => $material->title, 'type' => $material->type, 'id' => $material->id],
                ];
            }
        }

        if (!$topicId) {
            return $empty;
        }

        // Lesson pages
        $pages = LessonPage::where('activity_id', $topicId)
            ->orderBy('sort_order')
            ->get();

        if ($pages->isNotEmpty()) {
            $activity = Activity::find($topicId);
            $title    = $activity->name ?? 'Lesson';
            $content  = $pages->pluck('content')->implode("\n\n");

            return [
                'title'   => $title,
                'type'    => 'lesson',
                'content' => substr(strip_tags($content), 0, self::MAX_CONTENT_CHARS),
                'source'  => ['title' => $title, 'type' => 'lesson', 'id' => $topicId],
            ];
        }

        // Course materials linked to activity
        $material = CourseMaterial::where('activity_id', $topicId)
            ->where('processing_status', 'completed')
            ->whereNotNull('extracted_text')
            ->orderByDesc('processed_at')
            ->first();

        if ($material) {
            return [
                'title'   => $material->title,
                'type'    => $material->type,
                'content' => $material->extracted_text,
                'source'  => ['title' => $material->title, 'type' => $material->type, 'id' => $material->id],
            ];
        }

        return $empty;
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /ai/resources
    //  Find related YouTube videos and curated resources
    // ═══════════════════════════════════════════════════════════════

    public function resources(Request $request): JsonResponse
    {
        $request->validate([
            'topic_id'  => 'nullable|string',
            'course_id' => 'nullable|string',
            'query'     => 'required|string|max:500',
        ]);

        $topicId  = $request->input('topic_id');
        $courseId  = $request->input('course_id');
        $rawQuery = $request->input('query');

        // Rate limiting (YouTube search only — no external AI)
        $student = $request->user();
        if ($rateLimited = $this->checkRateLimit($student->id)) {
            return $rateLimited;
        }

        // Strip common chip/action phrases that aren't useful search terms
        $cleanQuery = preg_replace(
            '/find (related|me)?\s*videos?|related videos?|suggest.*videos?|show.*videos?|give me.*video/i',
            '',
            $rawQuery
        );
        // Strip leading emoji / non-word symbols (chip prefixes like 🎬 📋 🗂️ etc.)
        $cleanQuery = preg_replace('/^[\p{So}\s]+/u', '', $cleanQuery);
        $cleanQuery = trim($cleanQuery);

        // Discard generic leftover words from chip labels (e.g. "resources")
        if ($cleanQuery && preg_match('/^(resources?|materials?|links?)$/i', $cleanQuery)) {
            $cleanQuery = '';
        }

        // Build search term from topic + course context (not raw chip text)
        $searchTerm = $cleanQuery ?: $rawQuery;
        $topicName  = null;
        $courseName = null;

        if ($topicId) {
            $activity = Activity::with('course')->find($topicId);
            if ($activity) {
                $topicName  = $activity->name;
                $courseName = $activity->course?->name;
                $courseId    = $courseId ?: $activity->course_id;
            }
        }
        if (!$courseName && $courseId) {
            $courseName = Course::find($courseId)?->name;
        }

        // Prioritize: topic name + course name → much better YouTube results
        if ($topicName && $courseName) {
            $searchTerm = "{$topicName} {$courseName} tutorial";
        } elseif ($topicName) {
            $searchTerm = "{$topicName} tutorial";
        } elseif ($courseName && $cleanQuery) {
            $searchTerm = "{$courseName} {$cleanQuery}";
        } elseif ($courseName) {
            $searchTerm = "{$courseName} lecture tutorial";
        }

        $resources = $this->getRelatedResources($courseId, $topicId, $searchTerm);

        $responseText = count($resources) > 0
            ? "Here are some resources I found to help you:"
            : "I couldn't find specific resources right now. Try searching YouTube for: \"{$searchTerm}\"";

        return response()->json([
            'response'  => $responseText,
            'resources' => $resources,
            'meta'      => [
                'status'        => 'ok',
                'search_term'   => $searchTerm,
                'results_count' => count($resources),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /ai/flashcards
    //  Generate flashcards from current topic content
    // ═══════════════════════════════════════════════════════════════

    public function flashcards(Request $request): JsonResponse
    {
        $request->validate([
            'question'  => 'required|string|max:2000',
            'topic_id'  => 'nullable|string',
            'course_id' => 'nullable|string',
        ]);

        $student = $request->user();
        $topicId = $request->input('topic_id');
        $courseId = $request->input('course_id');

        if ($rateLimited = $this->checkRateLimit($student->id)) {
            return $rateLimited;
        }
        if ($extLimited = $this->checkExternalRateLimit()) {
            return $extLimited;
        }

        // Gather content — prefer topic content, fall back to student message
        $content = $this->getTopicContent($topicId);
        if (empty($content)) {
            $content = $request->input('question');
        }

        [$courseName, $courseId] = $this->resolveCourseContext($courseId, $topicId);
        $profile = $this->buildStudentProfile($student->id, $courseId);

        try {
            $response = $this->gemini->generateFlashcards($content, $courseName, $profile);
            $this->incrementUsage($student->id);

            return $this->successResponse($response, 'study', 'flashcard', $profile);
        } catch (\Exception $e) {
            return $this->handleAiError($e, 'flashcards');
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /ai/debug
    //  Help debug code with Socratic hints
    // ═══════════════════════════════════════════════════════════════

    public function debug(Request $request): JsonResponse
    {
        $request->validate([
            'question'  => 'required|string|max:4000',
            'topic_id'  => 'nullable|string',
            'course_id' => 'nullable|string',
        ]);

        $student = $request->user();
        $courseId = $request->input('course_id');
        $topicId = $request->input('topic_id');

        if ($rateLimited = $this->checkRateLimit($student->id)) {
            return $rateLimited;
        }
        if ($extLimited = $this->checkExternalRateLimit()) {
            return $extLimited;
        }

        [$courseName, $courseId] = $this->resolveCourseContext($courseId, $topicId);
        $profile = $this->buildStudentProfile($student->id, $courseId);

        try {
            $response = $this->gemini->debugCode(
                $request->input('question'),
                $courseName,
                $profile
            );
            $this->incrementUsage($student->id);

            return $this->successResponse($response, 'study', 'debug', $profile);
        } catch (\Exception $e) {
            return $this->handleAiError($e, 'debug');
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  POST /ai/motivate
    //  Emotional support + concrete next steps
    // ═══════════════════════════════════════════════════════════════

    public function motivate(Request $request): JsonResponse
    {
        $request->validate([
            'question'  => 'required|string|max:2000',
            'course_id' => 'nullable|string',
            'topic_id'  => 'nullable|string',
        ]);

        $student = $request->user();
        $courseId = $request->input('course_id');

        if ($rateLimited = $this->checkRateLimit($student->id)) {
            return $rateLimited;
        }
        if ($extLimited = $this->checkExternalRateLimit()) {
            return $extLimited;
        }

        $courseName = 'your studies';
        if ($courseId) {
            $courseName = Course::find($courseId)?->name ?? $courseName;
        }

        $profile = $this->buildStudentProfile($student->id, $courseId);

        try {
            $response = $this->gemini->motivateStudent(
                $request->input('question'),
                $courseName,
                $profile
            );
            $this->incrementUsage($student->id);

            return $this->successResponse($response, 'general', 'motivate', $profile);
        } catch (\Exception $e) {
            return $this->handleAiError($e, 'motivate');
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRIVATE — Adaptive Student Profile (Intelligence Layer)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Build a comprehensive student profile for adaptive AI responses.
     * Merges: Enrollment progress + LearnerProfile HATC + Risk assessment.
     */
    private function buildStudentProfile(string $studentId, ?string $courseId): array
    {
        $profile = [
            'tms'            => 0.5,   // Topic Mastery Score (0–1)
            'risk_level'     => 'none',
            'progress'       => '0%',
            'learning_style' => null,   // H | A | T | C
            'weeks_active'   => 0,
        ];

        if (!$courseId) {
            return $profile;
        }

        // ── Enrollment progress ──────────────────────────────────────
        $enrollment = Enrollment::where('user_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        if ($enrollment) {
            $progress = $enrollment->progress ?? 0;
            $profile['progress'] = round($progress, 1) . '%';
            $profile['tms']      = min(1.0, $progress / 100);

            if ($enrollment->enrolled_date) {
                $profile['weeks_active'] = (int) now()->diffInWeeks($enrollment->enrolled_date);
            }
        }

        // ── LearnerProfile HATC dimensions ───────────────────────────
        $learnerProfile = LearnerProfile::where('learner_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        if ($learnerProfile) {
            $scores = array_filter([
                (float) $learnerProfile->h_score,
                (float) $learnerProfile->a_score,
                (float) $learnerProfile->t_score,
                (float) $learnerProfile->c_score,
            ], fn ($s) => $s > 0);

            if (count($scores) > 0) {
                $profile['tms'] = round(array_sum($scores) / count($scores), 2);
            }

            $profile['learning_style'] = $learnerProfile->primary_profile;
        }

        // ── At-risk assessment ───────────────────────────────────────
        $riskRecord = AiAtRiskStudent::where('student_id', $studentId)
            ->where('course_id', $courseId)
            ->latest('detected_at')
            ->first();

        if ($riskRecord) {
            $profile['risk_level'] = $riskRecord->risk_level ?? 'none';

            // High-risk students get simpler explanations
            if ($riskRecord->risk_level === 'high' && $profile['tms'] > 0.4) {
                $profile['tms'] = 0.35;
            }
        }

        return $profile;
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRIVATE — Content Resolution
    // ═══════════════════════════════════════════════════════════════

    /**
     * Gather text content for a given topic (activity).
     * Priority: Lesson pages → Processed course materials → empty.
     */
    private function getTopicContent(?string $topicId): string
    {
        if (!$topicId) {
            return '';
        }

        // Priority 1: Lesson pages (richest structured content)
        $pages = LessonPage::where('activity_id', $topicId)
            ->orderBy('sort_order')
            ->get();

        if ($pages->isNotEmpty()) {
            $raw = $pages->pluck('content')->implode("\n\n");
            return substr(strip_tags($raw), 0, self::MAX_CONTENT_CHARS);
        }

        // Priority 2: Processed materials
        $material = CourseMaterial::where('activity_id', $topicId)
            ->where('processing_status', 'completed')
            ->whereNotNull('extracted_text')
            ->orderByDesc('processed_at')
            ->first();

        if ($material) {
            return substr($material->extracted_text, 0, self::MAX_CONTENT_CHARS);
        }

        return '';
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRIVATE — Context Helpers
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get latest quiz score for personalized remediation greeting.
     */
    private function getLatestQuizScore(string $studentId, ?string $topicId): ?int
    {
        if (!$topicId) {
            return null;
        }

        $attempt = QuizAttempt::where('student_id', $studentId)
            ->where('activity_id', $topicId)
            ->whereNotNull('score')
            ->latest('submitted_at')
            ->first();

        if ($attempt && $attempt->score !== null) {
            return (int) round($attempt->score);
        }

        return null;
    }

    /**
     * Build a contextual greeting for study mode.
     */
    private function buildStudyGreeting(?string $courseId, ?string $topicId): string
    {
        if ($topicId) {
            $activity = Activity::find($topicId);
            if ($activity) {
                return "📖 {$activity->name} — let's master this!";
            }
        }

        if ($courseId) {
            $course = Course::find($courseId);
            if ($course) {
                return "📚 {$course->name} — let's dive in!";
            }
        }

        return "📚 Study mode — I'm ready to help you learn.";
    }

    // ═══════════════════════════════════════════════════════════════
    //  PRIVATE — Resource Discovery
    // ═══════════════════════════════════════════════════════════════

    /**
     * Get related resources with priority: instructor-curated → YouTube API.
     * Returns max 3 results to keep the widget UI clean.
     */
    private function getRelatedResources(?string $courseId, ?string $topicId, ?string $searchTerm = null): array
    {
        $resources = [];

        // 1. Instructor-curated resources (highest trust)
        if ($courseId) {
            $curated = AiContentRecommendation::where('course_id', $courseId)
                ->where('content_type', 'youtube')
                ->orderBy('relevance_score', 'desc')
                ->take(2)
                ->get()
                ->map(fn ($r) => [
                    'title'         => $r->title,
                    'url'           => $r->url,
                    'thumbnail_url' => null,
                    'description'   => $r->source ?? '',
                    'curated'       => true,
                ])
                ->toArray();

            $resources = array_merge($resources, $curated);
        }

        // 2. Fill remaining slots with YouTube API search
        $remaining = 5 - count($resources);
        if ($remaining > 0 && $searchTerm) {
            $ytResults = $this->searchYouTube($searchTerm, $remaining);
            $resources = array_merge($resources, $ytResults);
        }

        return array_slice($resources, 0, 5);
    }

    /**
     * Search YouTube Data API v3 for educational content.
     * Results are cached for 1 hour per query to conserve quota.
     */
    private function searchYouTube(string $query, int $limit = 5): array
    {
        $apiKey = config('services.youtube.api_key');

        if (empty($apiKey)) {
            return [];
        }

        // Cache YouTube results to reduce quota consumption
        $cacheKey = 'yt_search:' . md5($query . $limit);
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $response = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/search', [
                'part'              => 'snippet',
                'q'                 => $query . ' tutorial university lecture',
                'type'              => 'video',
                'maxResults'        => $limit,
                'key'               => $apiKey,
                'relevanceLanguage' => 'en',
                'safeSearch'        => 'strict',
                'videoEmbeddable'   => 'true',
            ]);

            if ($response->failed()) {
                Log::warning('YouTube search failed', ['status' => $response->status()]);
                return [];
            }

            $items = $response->json('items', []);

            $results = array_map(fn ($item) => [
                'title'         => html_entity_decode($item['snippet']['title'] ?? '', ENT_QUOTES),
                'url'           => 'https://www.youtube.com/watch?v=' . ($item['id']['videoId'] ?? ''),
                'thumbnail_url' => $item['snippet']['thumbnails']['medium']['url']
                                    ?? $item['snippet']['thumbnails']['default']['url']
                                    ?? null,
                'description'   => substr($item['snippet']['description'] ?? '', 0, 120),
                'channel'       => $item['snippet']['channelTitle'] ?? '',
                'curated'       => false,
            ], $items);

            // Cache for 1 hour
            Cache::put($cacheKey, $results, now()->addHour());

            return $results;
        } catch (\Throwable $e) {
            Log::warning('YouTube search error', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
