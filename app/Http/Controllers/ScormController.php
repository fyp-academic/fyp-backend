<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ScormTrack;

class ScormController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/scorm-tracks
     * List SCORM tracking data for an activity (optionally filtered by student).
     */
    public function index(Request $request, string $id): JsonResponse
    {
        Activity::findOrFail($id);

        $query = ScormTrack::where('activity_id', $id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->query('student_id'));
        }

        $tracks = $query->with('student')
            ->orderBy('attempt')
            ->get();

        return response()->json(['data' => $tracks, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/scorm-tracks
     * Record or update a SCORM tracking element.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'element'    => 'required|string|max:255',
            'value'      => 'required|string',
            'attempt'    => 'sometimes|integer|min:1',
            'status'     => 'sometimes|nullable|string|in:not attempted,incomplete,completed,passed,failed',
            'score_raw'  => 'sometimes|nullable|numeric',
            'score_max'  => 'sometimes|nullable|numeric',
            'total_time' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $user = $request->user();

        $track = ScormTrack::updateOrCreate(
            [
                'activity_id' => $id,
                'student_id'  => $user->id,
                'attempt'     => $request->input('attempt', 1),
                'element'     => $request->element,
            ],
            [
                'id'         => Str::uuid()->toString(),
                'value'      => $request->value,
                'status'     => $request->input('status'),
                'score_raw'  => $request->input('score_raw'),
                'score_max'  => $request->input('score_max'),
                'total_time' => $request->input('total_time'),
            ]
        );

        return response()->json(['message' => 'Track recorded.', 'data' => $track]);
    }

    /**
     * GET /api/v1/activities/{id}/scorm-tracks/summary
     * Get a summary of SCORM progress for the authenticated student.
     */
    public function summary(Request $request, string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $user = $request->user();

        $tracks = ScormTrack::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->orderBy('attempt', 'desc')
            ->get();

        $latestAttempt = $tracks->first();

        return response()->json([
            'activity_id'    => $id,
            'student_id'     => $user->id,
            'total_attempts' => $tracks->max('attempt') ?? 0,
            'latest_status'  => $latestAttempt?->status,
            'latest_score'   => $latestAttempt?->score_raw,
            'score_max'      => $latestAttempt?->score_max,
        ]);
    }
}
