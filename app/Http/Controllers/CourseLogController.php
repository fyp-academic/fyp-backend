<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LearnerActivityEvent;
use App\Services\ActivityLogPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Moodle-style course activity log of student actions, built from
 * `learner_activity_events` and enriched by ActivityLogPresenter.
 */
class CourseLogController extends Controller
{
    public function __construct(private ActivityLogPresenter $presenter) {}

    /**
     * GET /api/v1/instructor/courses/{courseId}/logs
     */
    public function index(Request $request, string $courseId): JsonResponse
    {
        Course::findOrFail($courseId);

        $perPage = min((int) $request->input('per_page', 50), 200);
        $events  = $this->baseQuery($request, $courseId)->paginate($perPage);

        $rows = $this->presenter->present(collect($events->items()));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page'    => $events->lastPage(),
                'per_page'     => $events->perPage(),
                'total'        => $events->total(),
            ],
            'components' => ActivityLogPresenter::components(),
        ]);
    }

    /**
     * GET /api/v1/instructor/courses/{courseId}/logs/export
     * Streams the filtered log as CSV (no pagination, capped).
     */
    public function export(Request $request, string $courseId): StreamedResponse
    {
        $course = Course::findOrFail($courseId);

        $events = $this->baseQuery($request, $courseId)->limit(10000)->get();
        $rows   = $this->presenter->present($events);

        $filename = 'course-logs-' . ($course->short_name ?? $course->id) . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Time', 'User', 'Email', 'Event context', 'Component', 'Event name', 'Description', 'Origin', 'IP address']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    optional($r['time'])->toIso8601String(),
                    $r['user_name'],
                    $r['user_email'],
                    $r['context'],
                    $r['component'],
                    $r['event_name'],
                    $r['description'],
                    $r['origin'],
                    $r['ip_address'],
                ]);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Shared filtered query. Excludes pure-telemetry heartbeats (not real actions).
     */
    private function baseQuery(Request $request, string $courseId)
    {
        $query = LearnerActivityEvent::where('course_id', $courseId)
            ->where('event_type', '!=', 'heartbeat')
            ->with(['user:id,name,email,role', 'loginSession:id,ip_address,browser,os'])
            ->orderByDesc('occurred_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        } elseif ($request->filled('component')) {
            $types = ActivityLogPresenter::eventTypesForComponent($request->input('component'));
            $query->whereIn('event_type', $types ?: ['__none__']);
        }

        if ($request->filled('date_from')) {
            $query->where('occurred_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            // inclusive end-of-day when only a date is supplied
            $to = $request->input('date_to');
            $query->where('occurred_at', '<=', strlen($to) <= 10 ? $to . ' 23:59:59' : $to);
        }

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->whereHas('user', fn($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%"));
        }

        return $query;
    }
}
