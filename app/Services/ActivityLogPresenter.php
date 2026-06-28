<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\ForumDiscussion;
use App\Models\LearnerActivityEvent;
use App\Models\Section;
use App\Models\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Turns raw `learner_activity_events` into Moodle-style log rows:
 * who did what, when, where (event context), with which component, from where (origin/IP).
 *
 * All enrichment is read-side — no extra columns are stored. Resource names are
 * resolved in batch (one query per underlying model) to avoid N+1.
 */
class ActivityLogPresenter
{
    /**
     * event_type => [component, event_name, verb]
     * `verb` is used to synthesise a natural-language description.
     */
    private const EVENT_MAP = [
        'content_view'       => ['Activity',   'Course module viewed',     'viewed'],
        'content_skip'       => ['Activity',   'Course module skipped',    'skipped'],
        'page_view'          => ['System',     'Page viewed',              'viewed the page'],

        'video_play'         => ['Media',      'Video played',             'played the video'],
        'video_pause'        => ['Media',      'Video paused',             'paused the video'],
        'video_seek'         => ['Media',      'Video position changed',   'skipped within the video'],
        'video_complete'     => ['Media',      'Video completed',          'finished the video'],

        'pdf_open'           => ['File',       'File opened',              'opened'],
        'pdf_scroll'         => ['File',       'File read',                'read'],
        'material_download'  => ['File',       'File downloaded',          'downloaded'],

        'quiz_start'         => ['Quiz',       'Quiz attempt started',     'started a quiz attempt on'],
        'quiz_submit'        => ['Quiz',       'Quiz attempt submitted',   'submitted a quiz attempt on'],
        'quiz_question_skip' => ['Quiz',       'Quiz question skipped',    'skipped a question in'],

        'forum_post'         => ['Forum',      'Discussion created',       'created the discussion'],
        'forum_reply'        => ['Forum',      'Post created',             'replied in'],
        'forum_view'         => ['Forum',      'Discussion viewed',        'viewed the discussion'],

        'activity_complete'  => ['Completion', 'Activity completed',       'completed'],
        'practical_submit'   => ['Activity',   'Practical submitted',      'submitted the practical'],

        'login'              => ['Logins',     'User logged in',           'logged in'],
        'logout'             => ['Logins',     'User logged out',          'logged out'],

        'page_idle'          => ['System',     'User went idle',           'went idle on'],
        'tab_blur'           => ['System',     'Switched away',            'switched away from'],
        'tab_focus'          => ['System',     'Returned to tab',          'returned to'],

        'search'             => ['Search',     'Searched',                 'searched'],
    ];

    /** Context label per resource_type (Activity overrides with its own module type). */
    private const CONTEXT_LABEL = [
        'activity'   => 'Activity',
        'quiz'       => 'Quiz',
        'lesson'     => 'Lesson',
        'video'      => 'Video',
        'assignment' => 'Assignment',
        'material'   => 'File',
        'forum_post' => 'Forum',
        'session'    => 'Session',
        'section'    => 'Section',
        'course'     => 'Course',
    ];

    /**
     * resource_types backed by a dedicated model. Anything NOT listed here is
     * resolved via the Activity model (the student client sets resource_type to
     * the activity's own type — lesson/video/quiz/… — but resource_id is always
     * an Activity id).
     */
    private const SPECIAL_MODELS = [
        'material'   => [CourseMaterial::class, 'title'],
        'forum_post' => [ForumDiscussion::class, 'title'],
        'session'    => [Session::class, 'title'],
        'section'    => [Section::class, 'title'],
        'course'     => [Course::class, 'name'],
    ];

    /** Event types that carry no meaningful resource context. */
    private const CONTEXTLESS = ['login', 'logout', 'page_idle', 'tab_blur', 'tab_focus', 'page_view', 'search'];

    /**
     * @param  Collection<int,LearnerActivityEvent>  $events  (with `user` + `loginSession` eager-loaded)
     * @return array<int,array<string,mixed>>
     */
    public function present(Collection $events): array
    {
        $names = $this->resolveResourceNames($events);

        return $events->map(function (LearnerActivityEvent $e) use ($names) {
            [$component, $eventName, $verb] = self::EVENT_MAP[$e->event_type]
                ?? ['System', Str::title(str_replace('_', ' ', $e->event_type)), str_replace('_', ' ', $e->event_type)];

            $resolved   = $names[$e->resource_type][$e->resource_id] ?? null; // ['name'=>, 'type'=>]
            $context    = $this->buildContext($e, $resolved);
            $userName   = $e->user?->name ?? 'Unknown user';
            $session    = $e->loginSession;

            return [
                'id'          => $e->id,
                'time'        => $e->occurred_at,
                'user_id'     => $e->user_id,
                'user_name'   => $userName,
                'user_email'  => $e->user?->email,
                'role'        => $e->user?->role,
                'component'   => $component,
                'event_name'  => $eventName,
                'event_type'  => $e->event_type,
                'context'     => $context,
                'description' => $this->buildDescription($e, $userName, $verb, $resolved),
                'origin'      => $session ? ($e->device_type === 'desktop' ? 'web' : 'mobile') : 'system',
                // Prefer the IP captured directly on the event; fall back to the session.
                'ip_address'  => $e->ip_address ?? $session?->ip_address,
                'device_type' => $e->device_type,
                'browser'     => $session?->browser,
                'os'          => $session?->os,
                'value'       => $e->value,
                'metadata'    => $e->metadata,
            ];
        })->all();
    }

    /** Build the Moodle "Event context" string, e.g. "Quiz: Midterm Exam". */
    private function buildContext(LearnerActivityEvent $e, ?array $resolved): string
    {
        if (in_array($e->event_type, self::CONTEXTLESS, true)) {
            return 'System';
        }

        // Resource couldn't be resolved (null/deleted resource_id). Fall back to a
        // sensible label from the resource_type instead of a bare dash.
        if (!$resolved) {
            return $e->resource_type
                ? (self::CONTEXT_LABEL[$e->resource_type] ?? Str::title(str_replace('_', ' ', $e->resource_type)))
                : 'System';
        }

        // Activities expose their module type (quiz/assignment/lesson…) for a precise label.
        $label = $resolved['type']
            ? Str::title($resolved['type'])
            : (self::CONTEXT_LABEL[$e->resource_type] ?? 'Activity');

        return $label . ': ' . $resolved['name'];
    }

    private function buildDescription(LearnerActivityEvent $e, string $userName, string $verb, ?array $resolved): string
    {
        if (in_array($e->event_type, self::CONTEXTLESS, true) || !$resolved) {
            return trim("{$userName} {$verb}") . '.';
        }

        $desc = "{$userName} {$verb} '{$resolved['name']}'";

        // Append a measured metric where it adds meaning.
        if ($e->event_type === 'video_complete' || $e->event_type === 'video_play') {
            if ($e->value !== null) $desc .= " (at {$this->pct($e->value)})";
        } elseif ($e->event_type === 'pdf_scroll' && $e->value !== null) {
            $desc .= " (read {$this->pct($e->value)})";
        }

        return $desc . '.';
    }

    private function pct($value): string
    {
        return rtrim(rtrim(number_format((float) $value, 0), '0'), '.') . '%';
    }

    /**
     * Batch-resolve resource_id => ['name','type'] grouped by resource_type.
     * Activities and quizzes share the Activity model and are queried together.
     *
     * @return array<string,array<string,array{name:string,type:?string}>>
     */
    private function resolveResourceNames(Collection $events): array
    {
        // Gather ids per resource_type.
        $idsByType = [];
        foreach ($events as $e) {
            if ($e->resource_type && $e->resource_id && !in_array($e->event_type, self::CONTEXTLESS, true)) {
                $idsByType[$e->resource_type][$e->resource_id] = true;
            }
        }
        if (empty($idsByType)) return [];

        $names = [];

        // Special-model resource types (material/forum/session/section/course).
        foreach (self::SPECIAL_MODELS as $type => [$model, $col]) {
            $this->resolveInto($names, $idsByType, $type, $model, $col);
        }

        // Everything else is Activity-backed. Collect ids across all such
        // resource_types, query Activity once, and map back per type (the
        // Activity's own `type` gives a precise context label).
        $activityTypes = array_diff(array_keys($idsByType), array_keys(self::SPECIAL_MODELS));
        $allActivityIds = [];
        foreach ($activityTypes as $t) {
            $allActivityIds = array_merge($allActivityIds, array_keys($idsByType[$t]));
        }
        if ($allActivityIds) {
            $rows = Activity::whereIn('id', array_unique($allActivityIds))->get(['id', 'name', 'type'])->keyBy('id');
            foreach ($activityTypes as $t) {
                foreach (array_keys($idsByType[$t]) as $id) {
                    if ($row = $rows->get($id)) {
                        $names[$t][$id] = ['name' => $row->name, 'type' => $row->type];
                    }
                }
            }
        }

        return $names;
    }

    /** @param array<string,mixed> $names */
    private function resolveInto(array &$names, array $idsByType, string $type, string $model, string $col): void
    {
        $ids = array_keys($idsByType[$type] ?? []);
        if (!$ids) return;

        $rows = $model::whereIn('id', $ids)->get(['id', $col])->keyBy('id');
        foreach ($ids as $id) {
            if ($row = $rows->get($id)) $names[$type][$id] = ['name' => $row->{$col}, 'type' => null];
        }
    }

    /** event_type values that belong to a given component (for the component filter). */
    public static function eventTypesForComponent(string $component): array
    {
        return array_keys(array_filter(
            self::EVENT_MAP,
            fn($meta) => strcasecmp($meta[0], $component) === 0,
        ));
    }

    /** Distinct component names, for filter dropdowns. */
    public static function components(): array
    {
        return array_values(array_unique(array_map(fn($m) => $m[0], self::EVENT_MAP)));
    }
}
