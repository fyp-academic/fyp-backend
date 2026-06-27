<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\GradeItem;
use App\Models\PracticalSubmission;
use App\Models\QuizAttempt;
use App\Models\StudentGrade;
use App\Models\User;
use App\Services\GradeService;

class GradeController extends Controller
{
    public function __construct(private GradeService $grades) {}

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

        $student = User::findOrFail($request->student_id);

        $sg = $this->grades->recordGrade($gradeItem, $student, (float) $request->grade, $request->input('feedback'));

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

    /**
     * GET /api/v1/courses/{id}/my-grades
     * Return authenticated student's own grades for a course.
     */
    public function myGrades(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $course = Course::findOrFail($id);

        $grades = StudentGrade::where('student_id', $user->id)
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

        return response()->json(['data' => $grades, 'course_id' => $id]);
    }

    /**
     * GET /api/v1/my-gradebook
     * Aggregate the authenticated student's grades across EVERY gradable task type
     * (quizzes, assignments, practicals, interactive H5P/SCORM) and every course,
     * including auto-submitted attempts (timeout/violation). Read-only union built
     * from each type's own store — no single table covers all of them.
     */
    public function myGradebook(Request $request): JsonResponse
    {
        $uid = $request->user()->id;

        $pct = function ($grade, $max): ?float {
            if ($grade === null || $max === null || (float) $max <= 0) {
                return null;
            }
            return round((float) $grade / (float) $max * 100, 1);
        };

        $rows = [];

        // 1) Quizzes — quiz_attempts (score / max_score, auto_submitted)
        QuizAttempt::where('student_id', $uid)
            ->with(['activity:id,name', 'course:id,name'])
            ->get()
            ->each(function ($a) use (&$rows, $pct) {
                $rows[] = [
                    'id'             => 'quiz-' . $a->id,
                    'course_id'      => $a->course_id,
                    'course_name'    => $a->course?->name ?? 'Unknown course',
                    'activity_id'    => $a->activity_id,
                    'activity_name'  => $a->activity?->name ?? 'Quiz',
                    'type'           => 'quiz',
                    'grade'          => $a->score !== null ? (float) $a->score : null,
                    'grade_max'      => $a->max_score !== null ? (float) $a->max_score : null,
                    'percentage'     => $pct($a->score, $a->max_score),
                    'status'         => $a->status,
                    'submitted_at'   => $a->submitted_at,
                    'auto_submitted' => (bool) ($a->auto_submitted ?? false),
                    'late'           => false,
                    'graded'         => $a->score !== null,
                    'feedback'       => $a->feedback,
                ];
            });

        // 2) Assignments — assignment_submissions (grade, max from activity, late)
        AssignmentSubmission::where('student_id', $uid)
            ->with(['activity:id,name,grade_max', 'course:id,name'])
            ->get()
            ->each(function ($s) use (&$rows, $pct) {
                $max = $s->activity?->grade_max;
                $rows[] = [
                    'id'             => 'assignment-' . $s->id,
                    'course_id'      => $s->course_id,
                    'course_name'    => $s->course?->name ?? 'Unknown course',
                    'activity_id'    => $s->activity_id,
                    'activity_name'  => $s->activity?->name ?? 'Assignment',
                    'type'           => 'assignment',
                    'grade'          => $s->grade !== null ? (float) $s->grade : null,
                    'grade_max'      => $max !== null ? (float) $max : null,
                    'percentage'     => $pct($s->grade, $max),
                    'status'         => $s->status,
                    'submitted_at'   => $s->submitted_at,
                    'auto_submitted' => false,
                    'late'           => (bool) ($s->late ?? false),
                    'graded'         => $s->grade !== null,
                    'feedback'       => $s->feedback,
                ];
            });

        // 3) Practicals — practical_submissions (grade, max from activity, auto_submitted)
        PracticalSubmission::where('student_id', $uid)
            ->with(['activity:id,name,grade_max', 'course:id,name'])
            ->get()
            ->each(function ($s) use (&$rows, $pct) {
                $max = $s->activity?->grade_max;
                $rows[] = [
                    'id'             => 'practical-' . $s->id,
                    'course_id'      => $s->course_id,
                    'course_name'    => $s->course?->name ?? 'Unknown course',
                    'activity_id'    => $s->activity_id,
                    'activity_name'  => $s->activity?->name ?? 'Practical',
                    'type'           => 'practical',
                    'grade'          => $s->grade !== null ? (float) $s->grade : null,
                    'grade_max'      => $max !== null ? (float) $max : null,
                    'percentage'     => $pct($s->grade, $max),
                    'status'         => $s->grade !== null ? 'graded' : $s->status,
                    'submitted_at'   => $s->submitted_at,
                    'auto_submitted' => (bool) ($s->auto_submitted ?? false),
                    'late'           => false,
                    'graded'         => $s->grade !== null,
                    'feedback'       => $s->feedback,
                ];
            });

        // 4) Interactive content — student_grades for H5P/SCORM grade items only
        //    (practical/quiz/assignment items are covered above; excluded here to
        //    avoid double-counting since they also write a student_grades row).
        StudentGrade::where('student_id', $uid)
            ->whereHas('gradeItem', fn ($q) => $q->whereIn('activity_type', ['h5p', 'scorm']))
            ->with(['gradeItem.course:id,name'])
            ->get()
            ->each(function ($sg) use (&$rows) {
                $gi = $sg->gradeItem;
                $rows[] = [
                    'id'             => 'interactive-' . $sg->id,
                    'course_id'      => $gi?->course_id,
                    'course_name'    => $gi?->course?->name ?? 'Unknown course',
                    'activity_id'    => $gi?->activity_id,
                    'activity_name'  => $gi?->activity_name ?? 'Interactive activity',
                    'type'           => 'interactive',
                    'grade'          => $sg->grade !== null ? (float) $sg->grade : null,
                    'grade_max'      => $gi?->grade_max !== null ? (float) $gi->grade_max : null,
                    'percentage'     => $sg->percentage !== null ? (float) $sg->percentage : null,
                    'status'         => $sg->status,
                    'submitted_at'   => $sg->submitted_date,
                    'auto_submitted' => false,
                    'late'           => false,
                    'graded'         => $sg->grade !== null,
                    'feedback'       => $sg->feedback,
                ];
            });

        // Newest first across all types.
        usort($rows, fn ($a, $b) => strcmp((string) $b['submitted_at'], (string) $a['submitted_at']));

        return response()->json(['data' => array_values($rows)]);
    }
}
