<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\GradeItem;
use App\Models\StudentGrade;
use App\Models\User;

class GradeController extends Controller
{
    /**
     * GET /api/v1/courses/{id}/grades
     * Return all grade items and student submissions for a course.
     */
    public function index(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $gradeItems = GradeItem::where('course_id', $id)
            ->with('studentGrades')
            ->get()
            ->map(function ($gi) {
                return [
                    'id'           => $gi->id,
                    'activityName' => $gi->activity_name,
                    'activityType' => $gi->activity_type,
                    'gradeMax'     => $gi->grade_max,
                    'students'     => $gi->studentGrades->map(fn ($sg) => [
                        'studentId'     => $sg->student_id,
                        'studentName'   => $sg->student_name,
                        'grade'         => $sg->grade,
                        'percentage'    => $sg->percentage,
                        'feedback'      => $sg->feedback,
                        'submittedDate' => $sg->submitted_date,
                        'status'        => $sg->status,
                    ]),
                ];
            });

        return response()->json(['data' => $gradeItems, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/grade-items/{id}
     * Fetch a single grade item with all student grade records.
     */
    public function show(string $id): JsonResponse
    {
        $gradeItem = GradeItem::with('studentGrades')->findOrFail($id);

        return response()->json(['data' => [
            'id'           => $gradeItem->id,
            'activityName' => $gradeItem->activity_name,
            'activityType' => $gradeItem->activity_type,
            'gradeMax'     => $gradeItem->grade_max,
            'students'     => $gradeItem->studentGrades->map(fn ($sg) => [
                'studentId'     => $sg->student_id,
                'studentName'   => $sg->student_name,
                'grade'         => $sg->grade,
                'percentage'    => $sg->percentage,
                'feedback'      => $sg->feedback,
                'submittedDate' => $sg->submitted_date,
                'status'        => $sg->status,
            ]),
        ]]);
    }

    /**
     * POST /api/v1/grade-items/{id}/grades
     * Record or update a student's grade with optional written feedback.
     */
    public function submitGrade(Request $request, string $id): JsonResponse
    {
        $gradeItem = GradeItem::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'student_id' => 'required|string|exists:users,id',
            'grade'      => 'required|numeric|min:0',
            'feedback'   => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $student    = User::findOrFail($request->student_id);
        $percentage = $gradeItem->grade_max > 0
            ? round(($request->grade / $gradeItem->grade_max) * 100, 1)
            : 0;

        $sg = StudentGrade::updateOrCreate(
            ['grade_item_id' => $id, 'student_id' => $request->student_id],
            [
                'id'             => Str::uuid()->toString(),
                'student_name'   => $student->name,
                'grade'          => $request->grade,
                'percentage'     => $percentage,
                'feedback'       => $request->input('feedback'),
                'submitted_date' => now()->toDateString(),
                'status'         => 'graded',
            ]
        );

        return response()->json(['message' => 'Grade submitted.', 'data' => $sg], 201);
    }

    /**
     * GET /api/v1/courses/{id}/grades/student/{studentId}
     * Return all grades for a specific student across all grade items in a course.
     */
    public function studentGrades(string $id, string $studentId): JsonResponse
    {
        $course = Course::findOrFail($id);

        $grades = StudentGrade::where('student_id', $studentId)
            ->whereHas('gradeItem', fn ($q) => $q->where('course_id', $id))
            ->with('gradeItem')
            ->get()
            ->map(fn ($sg) => [
                'gradeItemId'   => $sg->grade_item_id,
                'activityName'  => $sg->gradeItem->activity_name ?? '',
                'activityType'  => $sg->gradeItem->activity_type ?? '',
                'gradeMax'      => $sg->gradeItem->grade_max ?? 0,
                'grade'         => $sg->grade,
                'percentage'    => $sg->percentage,
                'feedback'      => $sg->feedback,
                'submittedDate' => $sg->submitted_date,
                'status'        => $sg->status,
            ]);

        return response()->json(['data' => $grades, 'course_id' => $id, 'student_id' => $studentId]);
    }
}
