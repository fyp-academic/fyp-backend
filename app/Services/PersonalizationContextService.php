<?php

namespace App\Services;

use App\Models\BehavioralSignal;
use App\Models\CognitiveSignal;
use App\Models\LearnerProfile;
use App\Models\RiskScore;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\UserActivityCompletion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Unifies learner signals (HATC L0, computed profile, declared prefs, L1/L2 signals)
 * into a single context consumed by content, presentation, and navigation adaptation.
 */
class PersonalizationContextService
{
    public function __construct(
        private StudentProfileService $profileService,
    ) {}

    /**
     * Build a course-scoped personalization context for a student.
     *
     * @return array{
     *   student_id: string,
     *   course_id: string,
     *   content: array,
     *   presentation: array,
     *   navigation: array,
     * }
     */
    public function forCourse(string $studentId, string $courseId): array
    {
        $user = User::find($studentId);
        $studentProfile = StudentProfile::where('student_id', $studentId)->first();
        if (! $studentProfile) {
            $profileData = $this->profileService->recalculate($studentId);
            $studentProfile = StudentProfile::where('student_id', $studentId)->first();
        }

        $learnerProfile = LearnerProfile::where('learner_id', $studentId)
            ->where('course_id', $courseId)
            ->first();

        $behavioral = BehavioralSignal::where('learner_id', $studentId)
            ->where('course_id', $courseId)
            ->orderByDesc('week_number')
            ->first();

        $cognitive = CognitiveSignal::where('learner_id', $studentId)
            ->where('course_id', $courseId)
            ->orderByDesc('week_number')
            ->first();

        $risk = RiskScore::where('learner_id', $studentId)
            ->where('course_id', $courseId)
            ->orderByDesc('week_number')
            ->first();

        $weakTopics = $this->weakTopicsForCourse($studentId, $courseId, $studentProfile);
        $courseStats = $this->courseQuizStats($studentId, $courseId);

        $contentProfile = $this->buildContentProfile(
            $user,
            $studentProfile,
            $learnerProfile,
            $cognitive,
            $risk,
            $weakTopics,
            $courseStats,
            $this->isPersonalizationReady($studentId, $courseId),
            $this->weeksActive($studentId, $courseId),
        );

        $presentationService = app(PresentationAdaptationService::class);

        // Select AI presentation mode (cached 30 min) and merge into content profile for context
        $contentProfileWithIds = array_merge($contentProfile, [
            'student_id' => $studentId,
            'course_id'  => $courseId,
        ]);
        $presentationMode = $presentationService->selectMode($contentProfileWithIds);
        $presentation = $presentationService->resolve($contentProfile, $user, $risk, $presentationMode);

        $navigation = app(NavigationAdaptationService::class)
            ->resolve($studentId, $courseId, $contentProfile, $learnerProfile, $behavioral, $weakTopics);

        return [
            'student_id'  => $studentId,
            'course_id'   => $courseId,
            'content'     => $contentProfile,
            'presentation'=> $presentation,
            'navigation'  => $navigation,
        ];
    }

    /**
     * Content-level profile used by Gemini adaptation and chunk delivery.
     */
    public function contentProfileForAdaptation(string $studentId, ?string $courseId = null): array
    {
        $user = User::find($studentId);
        $studentProfile = StudentProfile::where('student_id', $studentId)->first();
        if (! $studentProfile) {
            $this->profileService->recalculate($studentId);
            $studentProfile = StudentProfile::where('student_id', $studentId)->first();
        }

        $learnerProfile = $courseId
            ? LearnerProfile::where('learner_id', $studentId)->where('course_id', $courseId)->first()
            : null;

        $cognitive = $courseId
            ? CognitiveSignal::where('learner_id', $studentId)->where('course_id', $courseId)->orderByDesc('week_number')->first()
            : CognitiveSignal::where('learner_id', $studentId)->orderByDesc('week_number')->first();

        $risk = $courseId
            ? RiskScore::where('learner_id', $studentId)->where('course_id', $courseId)->orderByDesc('week_number')->first()
            : RiskScore::where('learner_id', $studentId)->orderByDesc('week_number')->first();

        $weakTopics = $courseId
            ? $this->weakTopicsForCourse($studentId, $courseId, $studentProfile)
            : ($studentProfile?->weak_topics ?? []);

        // Course-scoped stats + warm-up gate. Without a course we cannot scope or
        // gate, so fall back to the global profile and treat personalization as ready.
        $courseStats = $courseId ? $this->courseQuizStats($studentId, $courseId) : [];
        $ready = $courseId ? $this->isPersonalizationReady($studentId, $courseId) : true;
        $weeksActive = $courseId ? $this->weeksActive($studentId, $courseId) : 0;

        return $this->buildContentProfile(
            $user,
            $studentProfile,
            $learnerProfile,
            $cognitive,
            $risk,
            $weakTopics,
            $courseStats,
            $ready,
            $weeksActive,
        );
    }

    private function buildContentProfile(
        ?User $user,
        ?StudentProfile $studentProfile,
        ?LearnerProfile $learnerProfile,
        ?CognitiveSignal $cognitive,
        ?RiskScore $risk,
        array $weakTopics,
        array $courseStats = [],
        bool $personalizationReady = true,
        int $weeksActive = 0,
    ): array {
        // Prefer course-scoped quiz performance over the global StudentProfile so
        // personalization reflects the specific course. Fall back to the global
        // profile when the student has no graded attempts in this course yet.
        $quizAverage = isset($courseStats['quiz_average']) && $courseStats['quiz_average'] !== null
            ? (float) $courseStats['quiz_average']
            : (float) ($studentProfile?->quiz_average ?? 0);
        $pace = ($courseStats['has_data'] ?? false)
            ? ($courseStats['pace'] ?? 'medium')
            : ($studentProfile?->pace ?? 'medium');
        $modality = $studentProfile?->preferred_modality ?? 'text';
        $completionRate = (float) ($studentProfile?->completion_rate ?? 0);

        $knowledgeLevel = match (true) {
            $quizAverage >= 80 => 'advanced',
            $quizAverage >= 60 => 'intermediate',
            default => 'novice',
        };

        if ($user?->pace_preference === 'accelerated' && $pace === 'medium') {
            $pace = 'fast';
        } elseif ($user?->pace_preference === 'guided' && $pace === 'medium') {
            $pace = 'slow';
        }

        // When no instructor-set LearnerProfile exists, synthesise a primary_profile
        // from the student's declared VARK style and pace preference so the AI mode
        // selector can reach all four player modes from day one without instructor setup.
        $primaryProfile = $learnerProfile?->primary_profile
            ?? $this->inferHatcFromUserDeclarations($user);

        return [
            'pace' => $pace,
            'quiz_average' => $quizAverage,
            'weak_topics' => $weakTopics,
            'preferred_modality' => $modality,
            'preferred_presentation_mode' => $studentProfile?->preferred_presentation_mode,
            'completion_rate' => $completionRate,
            'knowledge_level' => $knowledgeLevel,
            'primary_profile' => $primaryProfile,
            'declared_preferences' => $learnerProfile?->declared_preferences ?? [],
            'vark_style' => $user?->vark_style,
            'preferred_modes' => $user?->preferred_modes ?? [],
            'pace_preference' => $user?->pace_preference,
            'revisit_rate' => $cognitive?->content_revisit_rate,
            'quiz_learning_delta' => $cognitive?->quiz_learning_delta,
            'skip_rate' => $cognitive?->quiz_question_skip_rate,
            'risk_tier' => $risk?->tier ?? $risk?->risk_tier ?? null,
            'at_risk' => in_array($risk?->tier ?? $risk?->risk_tier ?? '', ['ORANGE', 'RED', 'AMBER'], true)
                || $quizAverage < 50,
            'personalization_ready' => $personalizationReady,
            'weeks_active' => max(0, $weeksActive),
        ];
    }

    /**
     * Course-scoped quiz performance for a student. Mirrors StudentProfileService's
     * global calculations but filtered to a single course via section.course_id.
     *
     * @return array{attempt_count: int, quiz_average: float|null, pace: string, has_data: bool}
     */
    private function courseQuizStats(string $studentId, string $courseId): array
    {
        $base = fn () => DB::table('quiz_attempts')
            ->join('activities', 'quiz_attempts.activity_id', '=', 'activities.id')
            ->join('sections', 'activities.section_id', '=', 'sections.id')
            ->where('quiz_attempts.student_id', $studentId)
            ->where('sections.course_id', $courseId);

        $attemptCount = $base()->count();

        $avg = $base()
            ->whereNotNull('quiz_attempts.score')
            ->whereNotNull('quiz_attempts.max_score')
            ->where('quiz_attempts.max_score', '>', 0)
            ->selectRaw('AVG((quiz_attempts.score * 1.0 / quiz_attempts.max_score) * 100) as avg_score')
            ->value('avg_score');

        $pace = 'medium';
        $studentTime = $base()->whereNotNull('quiz_attempts.time_spent')->avg('quiz_attempts.time_spent');
        $globalTime = DB::table('quiz_attempts')->whereNotNull('time_spent')->avg('time_spent');
        if ($studentTime !== null && $globalTime !== null && $globalTime > 0) {
            $ratio = $studentTime / $globalTime;
            $pace = $ratio > 1.5 ? 'slow' : ($ratio < 0.7 ? 'fast' : 'medium');
        }

        return [
            'attempt_count' => (int) $attemptCount,
            'quiz_average' => $avg !== null ? (float) $avg : null,
            'pace' => $pace,
            'has_data' => $avg !== null,
        ];
    }

    /**
     * Honest cold-start gate. Personalization activates only after the configured
     * warm-up window AND real activity evidence on the specific course.
     */
    public function isPersonalizationReady(string $studentId, string $courseId): bool
    {
        $warmupDays = (int) config('personalization.warmup_days', 7);
        $minQuiz = (int) config('personalization.min_quiz_attempts', 1);
        $minCompletions = (int) config('personalization.min_activity_completions', 3);

        // Need enrolment tenure first — no record / no date means no evidence yet.
        if ($this->weeksActive($studentId, $courseId, asDays: true) < $warmupDays) {
            return false;
        }

        $quizAttempts = DB::table('quiz_attempts')
            ->join('activities', 'quiz_attempts.activity_id', '=', 'activities.id')
            ->join('sections', 'activities.section_id', '=', 'sections.id')
            ->where('quiz_attempts.student_id', $studentId)
            ->where('sections.course_id', $courseId)
            ->count();

        if ($quizAttempts >= $minQuiz) {
            return true;
        }

        // Each row in user_activity_completions is itself a completion (no boolean
        // `completed` column exists — only completion_type/completed_at).
        $completions = DB::table('user_activity_completions')
            ->join('activities', 'user_activity_completions.activity_id', '=', 'activities.id')
            ->join('sections', 'activities.section_id', '=', 'sections.id')
            ->where('user_activity_completions.user_id', $studentId)
            ->where('sections.course_id', $courseId)
            ->distinct('user_activity_completions.activity_id')
            ->count('user_activity_completions.activity_id');

        return $completions >= $minCompletions;
    }

    /**
     * Tenure since enrolment in a course. Returns whole weeks by default, or whole
     * days when $asDays is true. Returns -1 (never ready) when no enrolment date.
     */
    private function weeksActive(string $studentId, string $courseId, bool $asDays = false): int
    {
        $enrolledDate = DB::table('enrollments')
            ->where('user_id', $studentId)
            ->where('course_id', $courseId)
            ->value('enrolled_date');

        if ($enrolledDate === null) {
            return -1;
        }

        $enrolled = Carbon::parse($enrolledDate);

        return $asDays
            ? (int) $enrolled->diffInDays(now())
            : (int) $enrolled->diffInWeeks(now());
    }

    /**
     * Map a student's declared VARK style and pace preference to the closest HATC letter
     * when no instructor-assessed LearnerProfile exists.
     *
     * H (Holistic)  — visual/overview-first learners
     * A (Analytical)— fast, accelerated learners who prefer depth
     * T (Thinking)  — guided/methodical learners
     * C (Caring)    — kinesthetic/example-based/collaborative learners
     */
    private function inferHatcFromUserDeclarations(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $vark  = $user->vark_style ?? '';
        $pace  = $user->pace_preference ?? '';
        $modes = (array) ($user->preferred_modes ?? []);

        if ($vark === 'visual' || in_array('multimedia', $modes, true)) {
            return 'H';
        }
        if ($vark === 'kinesthetic' || in_array('classroom', $modes, true) || in_array('live', $modes, true)) {
            return 'C';
        }
        if ($pace === 'accelerated' || in_array('self_paced', $modes, true)) {
            return 'A';
        }
        if ($pace === 'guided' || $vark === 'read_write') {
            return 'T';
        }

        return null;
    }

    private function weakTopicsForCourse(string $studentId, string $courseId, ?StudentProfile $profile): array
    {
        $globalWeak = $profile?->weak_topics ?? [];

        $courseWeak = DB::table('quiz_attempts')
            ->join('activities', 'quiz_attempts.activity_id', '=', 'activities.id')
            ->join('sections', 'activities.section_id', '=', 'sections.id')
            ->where('quiz_attempts.student_id', $studentId)
            ->where('sections.course_id', $courseId)
            ->whereNotNull('quiz_attempts.score')
            ->whereNotNull('quiz_attempts.max_score')
            ->whereRaw('(quiz_attempts.score * 1.0 / quiz_attempts.max_score) * 100 < 60')
            ->select('sections.title as topic_name')
            ->distinct()
            ->pluck('topic_name')
            ->toArray();

        return array_values(array_unique(array_merge($courseWeak, array_intersect($globalWeak, $courseWeak ?: $globalWeak))));
    }
}
