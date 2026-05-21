<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\EngagementScore;
use App\Models\LearnerActivityEvent;
use App\Models\LearnerLoginSession;
use App\Models\LearningStreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds 2 weeks of realistic engagement data for the 3 demo learners.
 *
 * Profiles:
 *  - John Mwale   (HIGH)   — daily logins, rich activity, scores 82-88
 *  - Grace Njau   (MEDIUM) — moderate activity, some gaps, scores 58-68
 *  - Peter Masanja(AT-RISK)— rare logins, bounce sessions, scores 25-38
 */
class EngagementDemoSeeder extends Seeder
{
    // Week windows (ISO week-like reference numbers)
    private const WEEK_1 = 18; // ~week ending 2 weeks ago
    private const WEEK_2 = 19; // ~week ending last week

    private array $students = [];
    private array $courses  = [];

    // Current date anchor (end of week 2 = yesterday at midnight)
    private Carbon $baseDate;

    public function run(): void
    {
        $this->command->info('Seeding engagement demo data...');

        $this->resolveStudents();
        $this->resolveCourses();

        // Wipe only engagement tables so we can re-run safely
        LearnerLoginSession::whereIn('user_id', array_column($this->students, 'id'))->delete();
        LearnerActivityEvent::whereIn('user_id', array_column($this->students, 'id'))->delete();
        EngagementScore::whereIn('learner_id', array_column($this->students, 'id'))->delete();
        LearningStreak::whereIn('learner_id', array_column($this->students, 'id'))->delete();

        // base = start of 2 weeks ago (Monday)
        $this->baseDate = now()->startOfWeek()->subWeeks(2);

        foreach ($this->students as $profile => $student) {
            $this->seedStudent($profile, $student['id']);
        }

        $this->command->info('Engagement demo data seeded successfully!');
    }

    // ─── Setup ────────────────────────────────────────────────────────────────

    private function resolveStudents(): void
    {
        $emails = [
            'high'   => 'john@demo.com',
            'medium' => 'grace@demo.com',
            'atrisk' => 'peter@demo.com',
        ];
        foreach ($emails as $key => $email) {
            $user = User::where('email', $email)->first();
            if (!$user) throw new \RuntimeException("Demo user {$email} not found. Run DemoDataSeeder first.");
            $this->students[$key] = ['id' => $user->id, 'name' => $user->name];
        }
    }

    private function resolveCourses(): void
    {
        $this->courses = Course::whereIn('short_name', ['CS 101', 'CS 201', 'CS 205'])
            ->pluck('id', 'short_name')
            ->toArray();

        if (count($this->courses) < 3) {
            throw new \RuntimeException('Demo courses not found. Run DemoDataSeeder first.');
        }
    }

    // ─── Per-student orchestration ────────────────────────────────────────────

    private function seedStudent(string $profile, string $userId): void
    {
        $config = $this->profileConfig($profile);

        foreach ([self::WEEK_1, self::WEEK_2] as $weekIndex => $weekNum) {
            $weekStart = $this->baseDate->copy()->addWeeks($weekIndex);

            // Days the learner was active this week
            $activeDays = $this->pickActiveDays($weekStart, $config['days_per_week']);

            foreach ($activeDays as $day) {
                $sessions = $this->seedLoginSessions($userId, $day, $config);
                $this->seedActivityEvents($userId, $day, $config, $sessions);
            }

            // Per-course engagement scores for this week
            foreach ($this->courses as $courseId) {
                $this->seedEngagementScore($userId, $courseId, $weekNum, $config, count($activeDays));
            }
        }

        // Learning streaks (one per course)
        foreach ($this->courses as $courseId) {
            $this->seedLearningStreak($userId, $courseId, $config);
        }
    }

    // ─── Login Sessions ───────────────────────────────────────────────────────

    private function seedLoginSessions(string $userId, Carbon $day, array $cfg): array
    {
        $sessions = [];
        $count = rand($cfg['sessions_per_day'][0], $cfg['sessions_per_day'][1]);

        for ($i = 0; $i < $count; $i++) {
            $hour      = $cfg['login_hours'][array_rand($cfg['login_hours'])];
            $startedAt = $day->copy()->setHour($hour)->setMinute(rand(0, 55));
            $duration  = rand($cfg['duration_range'][0], $cfg['duration_range'][1]);
            $isBounce  = $duration < 120;
            $endedAt   = $startedAt->copy()->addSeconds($duration);

            $session = LearnerLoginSession::create([
                'id'               => Str::uuid()->toString(),
                'user_id'          => $userId,
                'started_at'       => $startedAt,
                'ended_at'         => $endedAt,
                'duration_seconds' => $duration,
                'device_type'      => $cfg['devices'][array_rand($cfg['devices'])],
                'ip_address'       => $cfg['ip_address'],
                'hour_of_day'      => $hour,
                'is_bounce'        => $isBounce,
                'pages_visited'    => $isBounce ? rand(1, 3) : rand(4, 20),
            ]);

            $sessions[] = $session->id;
        }

        return $sessions;
    }

    // ─── Activity Events ──────────────────────────────────────────────────────

    private function seedActivityEvents(string $userId, Carbon $day, array $cfg, array $sessionIds): void
    {
        $eventPool = $cfg['event_pool'];
        $count     = rand($cfg['events_per_day'][0], $cfg['events_per_day'][1]);

        for ($i = 0; $i < $count; $i++) {
            $type      = $eventPool[array_rand($eventPool)];
            $courseId  = $this->courses[array_rand($this->courses)];
            $sessionId = !empty($sessionIds) ? $sessionIds[array_rand($sessionIds)] : null;

            [$resourceType, $value, $metadata] = $this->eventPayload($type);

            LearnerActivityEvent::create([
                'id'               => Str::uuid()->toString(),
                'user_id'          => $userId,
                'course_id'        => $courseId,
                'login_session_id' => $sessionId,
                'event_type'       => $type,
                'resource_type'    => $resourceType,
                'resource_id'      => null,
                'value'            => $value,
                'metadata'         => $metadata,
                'device_type'      => $cfg['devices'][array_rand($cfg['devices'])],
                'occurred_at'      => $day->copy()->setHour(rand(8, 22))->setMinute(rand(0, 59)),
            ]);
        }
    }

    private function eventPayload(string $type): array
    {
        return match ($type) {
            'video_play'      => ['video',    null,            ['action' => 'play']],
            'video_complete'  => ['video',    round(rand(70,100) / 100, 2), ['watch_percent' => rand(70,100)]],
            'video_pause'     => ['video',    null,            ['position_seconds' => rand(30, 600)]],
            'content_view'    => ['lesson',   rand(60, 1800),  ['scroll_depth' => round(rand(40,100)/100, 2)]],
            'pdf_open'        => ['material', null,            []],
            'pdf_scroll'      => ['material', round(rand(20,100)/100, 2), []],
            'quiz_start'      => ['quiz',     null,            []],
            'quiz_submit'     => ['quiz',     round(rand(40,100)/100, 2), ['score_percent' => rand(40,100)]],
            'forum_post'      => ['forum',    null,            ['word_count' => rand(30, 250)]],
            'forum_reply'     => ['forum',    null,            ['word_count' => rand(15, 120)]],
            'forum_view'      => ['forum',    null,            []],
            'activity_complete'=> ['activity', 1,              []],
            'tab_blur'        => [null,        null,           ['idle_seconds' => rand(30, 300)]],
            'page_idle'       => [null,        null,           ['idle_seconds' => rand(60, 600)]],
            default           => [null,        null,           []],
        };
    }

    // ─── Engagement Scores ────────────────────────────────────────────────────

    private function seedEngagementScore(
        string $userId, string $courseId, int $weekNum, array $cfg, int $activeDays
    ): void {
        $r = $cfg['score_range'];

        // Components — proportional to profile
        $ratio = ($r[0] + $r[1]) / 2 / 100; // normalised centre
        $L = $this->jitter($ratio, 0.10) * 100;
        $C = $this->jitter($ratio, 0.12) * 100;
        $A = $this->jitter($ratio, 0.15) * 100;
        $F = $this->jitter($ratio, 0.20) * 100;
        $P = $this->jitter($ratio, 0.10) * 100;
        $S = $this->jitter($ratio, 0.15) * 100;

        $final = EngagementScore::computeFromComponents($L, $C, $A, $F, $P, $S);
        $final = max($r[0], min($r[1], $final));

        // Week 2 score_delta based on week 1 if it exists
        $prev = EngagementScore::where('learner_id', $userId)
            ->where('course_id', $courseId)
            ->where('week_number', $weekNum - 1)
            ->value('engagement_score');

        EngagementScore::create([
            'id'                        => Str::uuid()->toString(),
            'learner_id'                => $userId,
            'course_id'                 => $courseId,
            'week_number'               => $weekNum,
            'login_consistency_score'   => round($L, 2),
            'content_completion_score'  => round($C, 2),
            'assessment_activity_score' => round($A, 2),
            'forum_participation_score' => round($F, 2),
            'pacing_score'              => round($P, 2),
            'live_session_score'        => round($S, 2),
            'engagement_score'          => round($final, 2),
            'previous_week_score'       => $prev,
            'score_delta'               => $prev !== null ? round($final - $prev, 2) : null,
            'component_breakdown'       => [
                'L' => round($L, 2), 'C' => round($C, 2),
                'A' => round($A, 2), 'F' => round($F, 2),
                'P' => round($P, 2), 'S' => round($S, 2),
            ],
            'computed_at'               => now(),
        ]);
    }

    // ─── Learning Streaks ─────────────────────────────────────────────────────

    private function seedLearningStreak(string $userId, string $courseId, array $cfg): void
    {
        LearningStreak::create([
            'id'                  => Str::uuid()->toString(),
            'learner_id'          => $userId,
            'course_id'           => $courseId,
            'current_streak_days' => $cfg['streak'][0],
            'longest_streak_days' => $cfg['streak'][1],
            'last_active_date'    => now()->subDay()->toDateString(),
            'streak_broken_at'    => $cfg['streak'][0] < 3 ? now()->subDays(5) : null,
        ]);
    }

    // ─── Profile Config ───────────────────────────────────────────────────────

    private function profileConfig(string $profile): array
    {
        return match ($profile) {

            // ── HIGH: John — daily, long sessions, many events ────────────────
            'high' => [
                'days_per_week'   => 6,
                'sessions_per_day'=> [1, 2],
                'duration_range'  => [3600, 9000],   // 1-2.5 hours
                'login_hours'     => [8, 9, 14, 15, 19, 20],
                'devices'         => ['desktop', 'desktop', 'mobile'],
                'ip_address'      => '192.168.1.101',
                'events_per_day'  => [8, 16],
                'event_pool'      => [
                    'content_view', 'content_view',
                    'video_play', 'video_complete',
                    'quiz_start', 'quiz_submit',
                    'forum_post', 'forum_reply',
                    'activity_complete',
                    'pdf_open', 'pdf_scroll',
                ],
                'score_range'     => [78, 92],
                'streak'          => [12, 14],
            ],

            // ── MEDIUM: Grace — 4-5 days/week, moderate activity ─────────────
            'medium' => [
                'days_per_week'   => 4,
                'sessions_per_day'=> [1, 1],
                'duration_range'  => [900, 3600],    // 15min - 1 hour
                'login_hours'     => [10, 13, 16, 20, 21],
                'devices'         => ['mobile', 'mobile', 'desktop'],
                'ip_address'      => '192.168.1.145',
                'events_per_day'  => [3, 7],
                'event_pool'      => [
                    'content_view',
                    'video_play', 'video_pause',
                    'quiz_start', 'quiz_submit',
                    'forum_view', 'forum_reply',
                    'pdf_open',
                    'tab_blur',
                ],
                'score_range'     => [54, 70],
                'streak'          => [5, 8],
            ],

            // ── AT-RISK: Peter — 1-2 days/week, short bouncy sessions ─────────
            'atrisk' => [
                'days_per_week'   => 2,
                'sessions_per_day'=> [1, 1],
                'duration_range'  => [60, 480],      // 1-8 minutes mostly
                'login_hours'     => [11, 22, 23],
                'devices'         => ['mobile'],
                'ip_address'      => '10.0.0.89',
                'events_per_day'  => [1, 3],
                'event_pool'      => [
                    'content_view',
                    'video_play', 'tab_blur',
                    'page_idle', 'forum_view',
                ],
                'score_range'     => [20, 40],
                'streak'          => [1, 3],
            ],

            default => throw new \InvalidArgumentException("Unknown profile: {$profile}"),
        };
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Pick $n distinct days from a Mon-Sun week, weighted toward weekdays */
    private function pickActiveDays(Carbon $weekStart, int $n): array
    {
        $all = [];
        for ($d = 0; $d < 7; $d++) {
            $all[] = $weekStart->copy()->addDays($d);
        }
        shuffle($all);
        return array_slice($all, 0, min($n, 7));
    }

    /** Add gaussian-like jitter to a ratio, clamped to [0,1] */
    private function jitter(float $center, float $spread): float
    {
        $delta = (mt_rand() / mt_getrandmax() - 0.5) * 2 * $spread;
        return max(0.0, min(1.0, $center + $delta));
    }
}
