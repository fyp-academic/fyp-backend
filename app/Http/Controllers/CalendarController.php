<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\Course;
use App\Services\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CalendarController extends Controller
{
    public function __construct(private CalendarService $calendar) {}

    /**
     * GET /api/v1/calendar/events?start=&end=&course_ids[]=
     * Merged feed: stored events (recurrence-expanded) + derived due dates + sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start'         => 'required|date',
            'end'           => 'required|date|after_or_equal:start',
            'course_ids'    => 'sometimes|array',
            'course_ids.*'  => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $items = $this->calendar->feedFor(
            $request->user(),
            \Carbon\Carbon::parse($request->input('start')),
            \Carbon\Carbon::parse($request->input('end')),
            $request->input('course_ids', [])
        );

        return response()->json(['data' => $items]);
    }

    /**
     * GET /api/v1/calendar/calendars
     * The personal + per-course calendars the user can toggle / file events under.
     */
    public function calendars(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->calendar->calendarsFor($request->user())]);
    }

    /**
     * POST /api/v1/calendar/events
     * Course events require an instructor/admin who owns the course; personal
     * events (course_id null) are allowed for any authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'               => 'required|string|max:255',
            'course_id'           => 'nullable|string|exists:courses,id',
            'description'         => 'nullable|string',
            'location'            => 'nullable|string|max:255',
            'all_day'             => 'nullable|boolean',
            'start_at'            => 'required|date',
            'end_at'              => 'nullable|date|after_or_equal:start_at',
            'recurrence_freq'     => 'nullable|in:none,daily,weekly,monthly,yearly',
            'recurrence_interval' => 'nullable|integer|min:1|max:52',
            'recurrence_until'    => 'nullable|date|after_or_equal:start_at',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($denied = $this->guardCourseWrite($request, $request->input('course_id'))) {
            return $denied;
        }

        $event = CalendarEvent::create([
            'user_id'             => $request->user()->id,
            'course_id'           => $request->input('course_id'),
            'title'               => $request->input('title'),
            'description'         => $request->input('description'),
            'location'            => $request->input('location'),
            'event_type'          => 'event',
            'all_day'             => $request->boolean('all_day'),
            'start_at'            => $request->input('start_at'),
            'end_at'              => $request->input('end_at'),
            'recurrence_freq'     => $request->input('recurrence_freq', 'none'),
            'recurrence_interval' => $request->input('recurrence_interval', 1),
            'recurrence_until'    => $request->input('recurrence_until'),
        ]);

        return response()->json(['data' => $event], 201);
    }

    /** GET /api/v1/calendar/events/{id} */
    public function show(Request $request, string $id): JsonResponse
    {
        $event = CalendarEvent::findOrFail($id);

        if (!$this->canManage($request, $event)) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => $event]);
    }

    /** PUT /api/v1/calendar/events/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $event = CalendarEvent::findOrFail($id);

        if (!$this->canManage($request, $event)) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'               => 'sometimes|required|string|max:255',
            'course_id'           => 'nullable|string|exists:courses,id',
            'description'         => 'nullable|string',
            'location'            => 'nullable|string|max:255',
            'all_day'             => 'nullable|boolean',
            'start_at'            => 'sometimes|required|date',
            'end_at'              => 'nullable|date|after_or_equal:start_at',
            'recurrence_freq'     => 'nullable|in:none,daily,weekly,monthly,yearly',
            'recurrence_interval' => 'nullable|integer|min:1|max:52',
            'recurrence_until'    => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // If the target course changes, re-check write permission on the new course.
        if ($request->exists('course_id') && $request->input('course_id') !== $event->course_id) {
            if ($denied = $this->guardCourseWrite($request, $request->input('course_id'))) {
                return $denied;
            }
        }

        $event->fill($request->only([
            'title', 'course_id', 'description', 'location', 'all_day',
            'start_at', 'end_at', 'recurrence_freq', 'recurrence_interval', 'recurrence_until',
        ]));
        $event->save();

        return response()->json(['data' => $event]);
    }

    /** DELETE /api/v1/calendar/events/{id} */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $event = CalendarEvent::findOrFail($id);

        if (!$this->canManage($request, $event)) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        $event->delete();

        return response()->json(['message' => 'Event deleted.']);
    }

    // ── Authorization helpers ───────────────────────────────────────────────

    /**
     * For a course-scoped write, the user must be an instructor/admin who owns
     * the course. Returns a JsonResponse to short-circuit on denial, or null.
     */
    private function guardCourseWrite(Request $request, ?string $courseId): ?JsonResponse
    {
        if ($courseId === null) {
            return null; // personal event — any authenticated user
        }

        $user = $request->user();
        if (!$user->isInstructor() && !$user->isAdmin()) {
            return response()->json(['error' => 'Only instructors can create course events.'], 403);
        }

        $course = Course::find($courseId);
        if (!$course || ($course->instructor_id !== $user->id && !$user->isAdmin())) {
            return response()->json(['error' => 'You do not own this course.'], 403);
        }

        return null;
    }

    /** Creator, or an admin, or the owning instructor of the event's course. */
    private function canManage(Request $request, CalendarEvent $event): bool
    {
        $user = $request->user();

        if ($event->user_id === $user->id || $user->isAdmin()) {
            return true;
        }

        if ($event->course_id !== null && ($user->isInstructor())) {
            return Course::where('id', $event->course_id)
                ->where('instructor_id', $user->id)
                ->exists();
        }

        return false;
    }
}
