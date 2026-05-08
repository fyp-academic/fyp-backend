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
            ->with(['student', 'grader'])
            ->orderBy('submitted_at', 'desc')
            ->get()
            ->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'activity_id' => $submission->activity_id,
                    'student_id' => $submission->student_id,
                    'student_name' => $submission->student ? $submission->student->name : 'Unknown',
                    'course_id' => $submission->course_id,
                    'status' => $submission->status,
                    'submission_text' => $submission->submission_text,
                    'file_path' => $submission->file_path,
                    'file_name' => $submission->file_name,
                    'file_size' => $submission->file_size,
                    'submitted_at' => $submission->submitted_at,
                    'grade' => $submission->grade,
                    'feedback' => $submission->feedback,
                    'graded_by' => $submission->graded_by,
                    'graded_at' => $submission->graded_at,
                    'attempt_number' => $submission->attempt_number,
                    'late' => $submission->late,
                ];
            });

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
            'file'            => 'sometimes|nullable|file|max:51200', // 50 MB max
            'file_path'       => 'sometimes|nullable|string',
            'file_name'       => 'sometimes|nullable|string|max:255',
            'file_size'       => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate that at least one submission type is provided
        $hasText = !empty($request->input('submission_text'));
        $hasFile = $request->hasFile('file') || !empty($request->input('file_path'));
        
        if (!$hasText && !$hasFile) {
            return response()->json(['errors' => ['submission' => 'Please provide either submission text or a file.']], 422);
        }

        $activity = Activity::findOrFail($id);
        $user     = $request->user();

        $lastAttempt = AssignmentSubmission::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->max('attempt_number') ?? 0;

        $filePath = $request->input('file_path');
        $fileName = $request->input('file_name');
        $fileSize = $request->input('file_size');

        // Handle uploaded file
        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $storedPath = $uploaded->store("submissions/{$activity->course_id}/{$id}", 'public');
            $filePath = $storedPath;
            $fileName = $uploaded->getClientOriginalName();
            $fileSize = $uploaded->getSize();
        }

        $submission = AssignmentSubmission::create([
            'id'              => Str::uuid()->toString(),
            'activity_id'     => $id,
            'student_id'      => $user->id,
            'course_id'       => $activity->course_id,
            'status'          => 'submitted',
            'submission_text' => $request->input('submission_text'),
            'file_path'       => $filePath,
            'file_name'       => $fileName,
            'file_size'       => $fileSize,
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
    public function show(Request $request, string $id): JsonResponse
    {
        $submission = AssignmentSubmission::with(['student', 'grader'])->findOrFail($id);
        $user = $request->user();

        // Check if user is the student who submitted, or an instructor/admin for the course
        $isOwner = $submission->student_id === $user->id;
        $isInstructor = $user->hasRole('instructor') || $user->hasRole('admin');
        
        if (!$isOwner && !$isInstructor) {
            return response()->json(['message' => 'Forbidden. You can only view your own submissions.'], 403);
        }

        return response()->json([
            'data' => [
                'id' => $submission->id,
                'activity_id' => $submission->activity_id,
                'student_id' => $submission->student_id,
                'student_name' => $submission->student ? $submission->student->name : 'Unknown',
                'course_id' => $submission->course_id,
                'status' => $submission->status,
                'submission_text' => $submission->submission_text,
                'file_path' => $submission->file_path,
                'file_name' => $submission->file_name,
                'file_size' => $submission->file_size,
                'submitted_at' => $submission->submitted_at,
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'graded_by' => $submission->graded_by,
                'graded_at' => $submission->graded_at,
                'attempt_number' => $submission->attempt_number,
                'late' => $submission->late,
            ]
        ]);
    }

    /**
     * PUT /api/v1/submissions/{id}/grade
     * Instructor grades a submission.
     */
    public function grade(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        // Only instructors and admins can grade
        if (!($user->hasRole('instructor') || $user->hasRole('admin'))) {
            return response()->json(['message' => 'Forbidden. Only instructors and admins can grade submissions.'], 403);
        }

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

    /**
     * GET /api/v1/my-submissions
     * Get all submissions for the current student.
     */
    public function mySubmissions(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = AssignmentSubmission::where('student_id', $user->id)
            ->with(['activity', 'course'])
            ->orderBy('submitted_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        $submissions = $query->get()->map(function ($submission) {
            return [
                'id' => $submission->id,
                'activity_id' => $submission->activity_id,
                'activity_title' => $submission->activity?->title ?? 'Unknown',
                'course_id' => $submission->course_id,
                'course_name' => $submission->course?->name ?? 'Unknown',
                'status' => $submission->status,
                'submission_text' => $submission->submission_text,
                'file_name' => $submission->file_name,
                'file_url' => $submission->file_path ? asset('storage/' . $submission->file_path) : null,
                'attempt_number' => $submission->attempt_number,
                'submitted_at' => $submission->submitted_at,
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'graded_at' => $submission->graded_at,
                'late' => $submission->late,
            ];
        });

        return response()->json(['data' => $submissions]);
    }
}
