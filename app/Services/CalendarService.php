<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\CalendarEvent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Session;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CalendarService
{
    /** Stable, high-contrast palette for per-course colors. */
    private const PALETTE = [
        '#2563eb', '#16a34a', '#9333ea', '#ea580c', '#0891b2',
        '#db2777', '#ca8a04', '#4f46e5', '#0d9488', '#dc2626',
        '#7c3aed', '#65a30d',
    ];

    private const PERSONAL_COLOR = '#64748b';

    /** Hard cap so a malformed recurrence can never blow up a request. */
    private const MAX_OCCURRENCES = 366;

    /**
     * Course ids the user may see on their calendar: courses they own (instructor)
     * plus courses they are actively enrolled in (student / TA).
     *
     * @return array<int,string>
     */
    public function accessibleCourseIds(User $user): array
    {
        $owned = Course::where('instructor_id', $user->id)->pluck('id')->all();

        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('course_id')
            ->all();

        return array_values(array_unique(array_merge($owned, $enrolled)));
    }

    /** Deterministic color for a course so both frontends always agree. */
    public function colorForCourse(?string $courseId): string
    {
        if ($courseId === null) {
            return self::PERSONAL_COLOR;
        }

        $idx = abs(crc32($courseId)) % count(self::PALETTE);
        return self::PALETTE[$idx];
    }

    /**
     * The "calendars" a user can toggle / file events under: a personal one,
     * plus one per accessible course. `editable` marks where the user may
     * create course-level events (instructors on their own courses).
     */
    public function calendarsFor(User $user): array
    {
        $courseIds = $this->accessibleCourseIds($user);
        $courses   = Course::whereIn('id', $courseIds)->get(['id', 'name', 'instructor_id']);

        $list = [[
            'id'       => 'personal',
            'name'     => 'Personal',
            'color'    => self::PERSONAL_COLOR,
            'editable' => true,
        ]];

        $canAuthorCourseEvents = $user->isInstructor() || $user->isAdmin();

        foreach ($courses as $course) {
            $list[] = [
                'id'       => $course->id,
                'name'     => $course->name,
                'color'    => $this->colorForCourse($course->id),
                'editable' => $canAuthorCourseEvents && $course->instructor_id === $user->id,
            ];
        }

        return $list;
    }

    /**
     * Merged calendar feed for [start, end]: stored events (recurrence-expanded)
     * + derived activity due dates + derived live sessions. Derived items are
     * read-only and deep-link back to their source. Single source of truth: we
     * never copy due dates / sessions into calendar_events.
     *
     * @param  array<int,string>  $courseFilter  optional subset of course ids to include
     */
    public function feedFor(User $user, Carbon $start, Carbon $end, array $courseFilter = []): array
    {
        $courseIds = $this->accessibleCourseIds($user);
        if (!empty($courseFilter)) {
            $courseIds = array_values(array_intersect($courseIds, $courseFilter));
        }

        $canAuthorCourseEvents = $user->isInstructor() || $user->isAdmin();
        $ownedCourseIds        = $canAuthorCourseEvents
            ? Course::where('instructor_id', $user->id)->pluck('id')->all()
            : [];

        $items = [];

        // ── Stored calendar events (course events + this user's personal) ──────
        $events = CalendarEvent::query()
            ->where(function ($q) use ($courseIds, $user) {
                $q->whereIn('course_id', $courseIds)
                  ->orWhere(fn ($p) => $p->whereNull('course_id')->where('user_id', $user->id));
            })
            ->where('start_at', '<=', $end)
            ->where(function ($q) use ($start) {
                // keep recurring rows whose series may still reach the window
                $q->whereNull('recurrence_until')
                  ->orWhere('recurrence_until', '>=', $start->copy()->toDateString())
                  ->orWhere('end_at', '>=', $start)
                  ->orWhere('start_at', '>=', $start);
            })
            ->with('course:id,name,instructor_id')
            ->get();

        foreach ($events as $event) {
            $editable = $event->user_id === $user->id
                || ($event->course_id !== null && in_array($event->course_id, $ownedCourseIds, true));

            $color = $event->color ?: $this->colorForCourse($event->course_id);

            foreach ($this->expandOccurrences($event, $start, $end) as [$occStart, $occEnd]) {
                $items[] = [
                    'id'          => 'event:' . $event->id . ':' . $occStart->timestamp,
                    'event_id'    => $event->id,
                    'source'      => 'event',
                    'title'       => $event->title,
                    'description' => $event->description,
                    'location'    => $event->location,
                    'start'       => $occStart->toIso8601String(),
                    'end'         => $occEnd?->toIso8601String(),
                    'all_day'     => (bool) $event->all_day,
                    'course_id'   => $event->course_id,
                    'course_name' => $event->course?->name ?? 'Personal',
                    'color'       => $color,
                    'editable'    => $editable,
                    'recurring'   => $event->recurrence_freq !== 'none',
                    'link'        => ['type' => 'calendar', 'course_id' => $event->course_id],
                ];
            }
        }

        if (!empty($courseIds)) {
            $courseNames = Course::whereIn('id', $courseIds)->pluck('name', 'id');

            // ── Derived: activity due dates (assignments, quizzes, etc.) ───────
            $activities = Activity::whereIn('course_id', $courseIds)
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->get(['id', 'course_id', 'type', 'name', 'due_date']);

            foreach ($activities as $activity) {
                $due = Carbon::parse($activity->due_date)->endOfDay();
                $items[] = [
                    'id'          => 'activity:' . $activity->id,
                    'source'      => in_array($activity->type, ['quiz', 'assignment'], true) ? $activity->type : 'deadline',
                    'title'       => $activity->name,
                    'start'       => $due->toIso8601String(),
                    'end'         => $due->toIso8601String(),
                    'all_day'     => true,
                    'course_id'   => $activity->course_id,
                    'course_name' => $courseNames[$activity->course_id] ?? '',
                    'color'       => $this->colorForCourse($activity->course_id),
                    'editable'    => false,
                    'recurring'   => false,
                    'link'        => [
                        'type'        => $activity->type,
                        'course_id'   => $activity->course_id,
                        'activity_id' => $activity->id,
                    ],
                ];
            }

            // ── Derived: scheduled live sessions ───────────────────────────────
            $sessions = Session::whereIn('course_id', $courseIds)
                ->where('status', '!=', 'ended')
                ->whereNotNull('scheduled_at')
                ->whereBetween('scheduled_at', [$start, $end])
                ->get(['id', 'course_id', 'title', 'scheduled_at', 'duration']);

            foreach ($sessions as $session) {
                $sStart = Carbon::parse($session->scheduled_at);
                $sEnd   = $sStart->copy()->addMinutes((int) ($session->duration ?: 60));
                $items[] = [
                    'id'          => 'session:' . $session->id,
                    'source'      => 'session',
                    'title'       => $session->title,
                    'start'       => $sStart->toIso8601String(),
                    'end'         => $sEnd->toIso8601String(),
                    'all_day'     => false,
                    'course_id'   => $session->course_id,
                    'course_name' => $courseNames[$session->course_id] ?? '',
                    'color'       => $this->colorForCourse($session->course_id),
                    'editable'    => false,
                    'recurring'   => false,
                    'link'        => [
                        'type'       => 'session',
                        'course_id'  => $session->course_id,
                        'session_id' => $session->id,
                    ],
                ];
            }
        }

        return $items;
    }

    /**
     * Expand a (possibly recurring) event into [start, end] Carbon pairs whose
     * start falls inside [windowStart, windowEnd].
     *
     * @return array<int,array{0:Carbon,1:?Carbon}>
     */
    private function expandOccurrences(CalendarEvent $event, Carbon $windowStart, Carbon $windowEnd): array
    {
        $baseStart = $event->start_at instanceof CarbonInterface ? $event->start_at->copy() : Carbon::parse($event->start_at);
        $duration  = $event->end_at ? $baseStart->diffInSeconds($event->end_at) : null;

        if ($event->recurrence_freq === 'none' || $event->recurrence_freq === null) {
            $baseEnd = $duration !== null ? $baseStart->copy()->addSeconds($duration) : $baseStart;
            // Overlaps the window?  start <= windowEnd AND end >= windowStart
            if ($baseStart->lessThanOrEqualTo($windowEnd) && $baseEnd->greaterThanOrEqualTo($windowStart)) {
                return [[$baseStart, $duration !== null ? $baseStart->copy()->addSeconds($duration) : null]];
            }
            return [];
        }

        $interval = max(1, (int) $event->recurrence_interval);
        $until     = $event->recurrence_until ? Carbon::parse($event->recurrence_until)->endOfDay() : null;
        $hardEnd   = $until && $until->lessThan($windowEnd) ? $until : $windowEnd;

        $occurrences = [];
        $cursor      = $baseStart->copy();
        $guard       = 0;

        // Fast-forward the cursor close to the window without unbounded looping.
        while ($cursor->lessThan($windowStart) && $guard < self::MAX_OCCURRENCES) {
            $cursor = $this->advance($cursor, $event->recurrence_freq, $interval);
            $guard++;
        }

        while ($cursor->lessThanOrEqualTo($hardEnd) && count($occurrences) < self::MAX_OCCURRENCES) {
            $occEnd = $duration !== null ? $cursor->copy()->addSeconds($duration) : null;
            $occurrences[] = [$cursor->copy(), $occEnd];
            $cursor = $this->advance($cursor, $event->recurrence_freq, $interval);
        }

        return $occurrences;
    }

    private function advance(Carbon $date, string $freq, int $interval): Carbon
    {
        return match ($freq) {
            'daily'   => $date->copy()->addDays($interval),
            'weekly'  => $date->copy()->addWeeks($interval),
            'monthly' => $date->copy()->addMonthsNoOverflow($interval),
            'yearly'  => $date->copy()->addYears($interval),
            default   => $date->copy()->addDay(),
        };
    }
}
