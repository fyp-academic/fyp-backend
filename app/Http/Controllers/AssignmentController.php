<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\AssignmentSubmission;

class AssignmentController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/submissions
     * List all submissions for an assignment activity.
     */
    public function index(string $id): JsonResponse
    {
        $activity    = Activity::findOrFail($id);
        $submissions = AssignmentSubmission::where('activity_id', $id)
            ->with('student')
            ->orderBy('submitted_at', 'desc')
            ->get();

        return response()->json(['data' => $submissions, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/submissions
     * Student submits work for an assignment.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'submission_text' => 'sometimes|nullable|string',
            'file_path'       => 'sometimes|nullable|string',
            'file_name'       => 'sometimes|nullable|string|max:255',
            'file_size'       => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::findOrFail($id);
        $user     = $request->user();

        $lastAttempt = AssignmentSubmission::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->max('attempt_number') ?? 0;

        $submission = AssignmentSubmission::create([
            'id'              => Str::uuid()->toString(),
            'activity_id'     => $id,
            'student_id'      => $user->id,
            'course_id'       => $activity->course_id,
            'status'          => 'submitted',
            'submission_text' => $request->input('submission_text'),
            'file_path'       => $request->input('file_path'),
            'file_name'       => $request->input('file_name'),
            'file_size'       => $request->input('file_size'),
            'submitted_at'    => now(),
            'attempt_number'  => $lastAttempt + 1,
            'late'            => $activity->due_date && now()->greaterThan($activity->due_date),
        ]);

        return response()->json(['message' => 'Submission created.', 'data' => $submission], 201);
    }

    /**
     * GET /api/v1/submissions/{id}
     * Show a single submission.
     */
    public function show(string $id): JsonResponse
    {
        $submission = AssignmentSubmission::with(['student', 'grader'])->findOrFail($id);

        return response()->json(['data' => $submission]);
    }

    /**
     * PUT /api/v1/submissions/{id}/grade
     * Instructor grades a submission.
     */
    public function grade(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grade'    => 'required|numeric|min:0',
            'feedback' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $submission = AssignmentSubmission::findOrFail($id);
        $submission->update([
            'grade'     => $request->grade,
            'feedback'  => $request->input('feedback'),
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
            'status'    => 'graded',
        ]);

        return response()->json(['message' => 'Submission graded.', 'data' => $submission]);
    }
}
