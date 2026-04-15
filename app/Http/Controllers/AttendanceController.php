<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\AttendanceSession;
use App\Models\AttendanceLog;

class AttendanceController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/attendance-sessions
     * List all sessions for an attendance activity.
     */
    public function sessions(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $sessions = AttendanceSession::where('activity_id', $id)
            ->withCount('logs')
            ->orderBy('session_date', 'desc')
            ->get();

        return response()->json(['data' => $sessions, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/attendance-sessions
     * Create a new attendance session.
     */
    public function createSession(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:255',
            'description'      => 'sometimes|nullable|string',
            'session_date'     => 'required|date',
            'duration_minutes' => 'sometimes|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::findOrFail($id);

        $session = AttendanceSession::create([
            'id'               => Str::uuid()->toString(),
            'activity_id'      => $id,
            'course_id'        => $activity->course_id,
            'title'            => $request->title,
            'description'      => $request->input('description'),
            'session_date'     => $request->session_date,
            'duration_minutes' => $request->input('duration_minutes', 60),
            'status'           => 'open',
        ]);

        return response()->json(['message' => 'Session created.', 'data' => $session], 201);
    }

    /**
     * GET /api/v1/attendance-sessions/{id}/logs
     * Retrieve attendance logs for a session.
     */
    public function logs(string $id): JsonResponse
    {
        AttendanceSession::findOrFail($id);
        $logs = AttendanceLog::where('session_id', $id)
            ->with('student')
            ->get();

        return response()->json(['data' => $logs, 'session_id' => $id]);
    }

    /**
     * POST /api/v1/attendance-sessions/{id}/logs
     * Record or update attendance for a student in a session.
     */
    public function recordAttendance(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|string|exists:users,id',
            'status'     => 'required|string|in:present,absent,late,excused',
            'remarks'    => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        AttendanceSession::findOrFail($id);

        $log = AttendanceLog::updateOrCreate(
            ['session_id' => $id, 'student_id' => $request->student_id],
            [
                'id'       => Str::uuid()->toString(),
                'status'   => $request->status,
                'remarks'  => $request->input('remarks'),
                'taken_by' => $request->user()->id,
            ]
        );

        return response()->json(['message' => 'Attendance recorded.', 'data' => $log]);
    }

    /**
     * POST /api/v1/attendance-sessions/{id}/logs/bulk
     * Record attendance for multiple students at once.
     */
    public function bulkRecord(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'records'            => 'required|array|min:1',
            'records.*.student_id' => 'required|string|exists:users,id',
            'records.*.status'     => 'required|string|in:present,absent,late,excused',
            'records.*.remarks'    => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        AttendanceSession::findOrFail($id);
        $takenBy = $request->user()->id;
        $saved   = [];

        foreach ($request->records as $record) {
            $saved[] = AttendanceLog::updateOrCreate(
                ['session_id' => $id, 'student_id' => $record['student_id']],
                [
                    'id'       => Str::uuid()->toString(),
                    'status'   => $record['status'],
                    'remarks'  => $record['remarks'] ?? null,
                    'taken_by' => $takenBy,
                ]
            );
        }

        return response()->json(['message' => 'Bulk attendance recorded.', 'data' => $saved]);
    }
}
