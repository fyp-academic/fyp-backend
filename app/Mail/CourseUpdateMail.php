<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class CourseUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  User   $student       The enrolled student receiving the notification
     * @param  string $courseName    Course title
     * @param  string $updateType    Type: new_material | assignment | quiz | live_session | grade_released
     * @param  string $activityName  Name of the new/updated activity
     * @param  string $description   Short description / instructions
     * @param  string|null $dueDate  ISO date string if applicable
     * @param  string $actionUrl     Deep-link back into the LMS
     */
    public function __construct(
        public readonly User    $student,
        public readonly string  $courseName,
        public readonly string  $updateType,
        public readonly string  $activityName,
        public readonly string  $description,
        public readonly ?string $dueDate,
        public readonly string  $actionUrl,
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'new_material'   => "New material posted in {$this->courseName}",
            'assignment'     => "New assignment in {$this->courseName}",
            'quiz'           => "New quiz available in {$this->courseName}",
            'live_session'   => "Live session scheduled for {$this->courseName}",
            'grade_released' => "Your grade is available in {$this->courseName}",
        ];

        return new Envelope(
            subject: $subjects[$this->updateType] ?? "Update in {$this->courseName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.course-update',
            with: [
                'studentName'  => $this->student->name,
                'courseName'   => $this->courseName,
                'updateType'   => $this->updateType,
                'activityName' => $this->activityName,
                'description'  => $this->description,
                'dueDate'      => $this->dueDate,
                'actionUrl'    => $this->actionUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
