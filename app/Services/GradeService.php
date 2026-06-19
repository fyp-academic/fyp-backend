<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\GradeItem;
use App\Models\StudentGrade;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Centralises grade-book writes so that grades produced from different sources
 * (manual grade-item entry, assignment grading, quiz auto-grading) all land in
 * the same student_grades table and are visible to students via my-grades.
 */
class GradeService
{
    /**
     * Get (or lazily create) the grade item that represents an activity in the
     * course grade book. Keeps activity_name / type / grade_max in sync.
     */
    public function gradeItemForActivity(Activity $activity, ?float $gradeMaxOverride = null): GradeItem
    {
        $item = GradeItem::firstOrNew(['activity_id' => $activity->id]);

        if (!$item->exists) {
            $item->id = Str::uuid()->toString();
        }

        $item->course_id     = $activity->course_id;
        $item->activity_name = $activity->name;
        $item->activity_type = $activity->type;
        $item->grade_max     = $gradeMaxOverride ?? $item->grade_max ?? $activity->grade_max ?? 100;
        $item->save();

        return $item;
    }

    /**
     * Record (insert or update) a student's grade for a grade item.
     * Fixes the previous bug where updateOrCreate reset the primary key on
     * every update by injecting a fresh UUID into the values array.
     */
    public function recordGrade(GradeItem $gradeItem, User $student, float $grade, ?string $feedback = null): StudentGrade
    {
        $percentage = $gradeItem->grade_max > 0
            ? round(($grade / $gradeItem->grade_max) * 100, 1)
            : 0;

        $sg = StudentGrade::firstOrNew([
            'grade_item_id' => $gradeItem->id,
            'student_id'    => $student->id,
        ]);

        if (!$sg->exists) {
            $sg->id = Str::uuid()->toString();
        }

        $sg->student_name   = $student->name;
        $sg->grade          = $grade;
        $sg->percentage     = $percentage;
        $sg->feedback       = $feedback;
        $sg->submitted_date = now()->toDateString();
        $sg->status         = 'graded';
        $sg->save();

        return $sg;
    }
}
