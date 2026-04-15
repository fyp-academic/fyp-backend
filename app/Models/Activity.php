<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Activity extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    /**
     * Allowed activity types (Moodle tool picker).
     */
    public const TYPES = [
        'assignment', 'attendance', 'bigbluebutton', 'book',
        'checklist', 'choice', 'certificate', 'database',
        'feedback', 'file', 'folder', 'forum',
        'glossary', 'h5p', 'ims_content_package', 'lesson',
        'page', 'quiz', 'scorm', 'text_and_media_area',
    ];

    protected $fillable = [
        'id', 'section_id', 'course_id', 'type', 'name', 'description',
        'due_date', 'visible', 'completion_status', 'grade_max', 'sort_order', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'visible'  => 'boolean',
            'due_date' => 'date',
            'settings' => 'array',
        ];
    }

    // ── Core relationships ─────────────────────────────────────────────

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function gradeItem(): HasOne
    {
        return $this->hasOne(GradeItem::class);
    }

    // ── Quiz ───────────────────────────────────────────────────────────

    public function quizQuestions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    // ── Assignment ─────────────────────────────────────────────────────

    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    // ── Attendance ─────────────────────────────────────────────────────

    public function attendanceSessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class);
    }

    // ── Book ───────────────────────────────────────────────────────────

    public function bookChapters(): HasMany
    {
        return $this->hasMany(BookChapter::class);
    }

    // ── Checklist ──────────────────────────────────────────────────────

    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    // ── Choice ─────────────────────────────────────────────────────────

    public function choiceOptions(): HasMany
    {
        return $this->hasMany(ChoiceOption::class);
    }

    public function choiceResponses(): HasMany
    {
        return $this->hasMany(ChoiceResponse::class);
    }

    // ── Certificate ────────────────────────────────────────────────────

    public function certificateTemplate(): HasOne
    {
        return $this->hasOne(CertificateTemplate::class);
    }

    // ── Database ───────────────────────────────────────────────────────

    public function databaseFields(): HasMany
    {
        return $this->hasMany(DatabaseField::class);
    }

    public function databaseEntries(): HasMany
    {
        return $this->hasMany(DatabaseEntry::class);
    }

    // ── Feedback ───────────────────────────────────────────────────────

    public function feedbackQuestions(): HasMany
    {
        return $this->hasMany(FeedbackQuestion::class);
    }

    // ── Folder ─────────────────────────────────────────────────────────

    public function folderFiles(): HasMany
    {
        return $this->hasMany(FolderFile::class);
    }

    // ── Forum ──────────────────────────────────────────────────────────

    public function forumDiscussions(): HasMany
    {
        return $this->hasMany(ForumDiscussion::class);
    }

    // ── Glossary ───────────────────────────────────────────────────────

    public function glossaryEntries(): HasMany
    {
        return $this->hasMany(GlossaryEntry::class);
    }

    // ── Lesson ─────────────────────────────────────────────────────────

    public function lessonPages(): HasMany
    {
        return $this->hasMany(LessonPage::class);
    }

    // ── SCORM ──────────────────────────────────────────────────────────

    public function scormTracks(): HasMany
    {
        return $this->hasMany(ScormTrack::class);
    }
}
