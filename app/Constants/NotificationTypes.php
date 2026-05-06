<?php

namespace App\Constants;

class NotificationTypes
{
    // Student notifications - Essential
    public const ASSIGNMENT_POSTED = 'assignment_posted';
    public const ASSIGNMENT_DUE_SOON = 'assignment_due_soon';
    public const ASSIGNMENT_OVERDUE = 'assignment_overdue';
    public const GRADE_RELEASED = 'grade_released';
    public const FEEDBACK_ADDED = 'feedback_added';
    public const NEW_COURSE_MATERIAL = 'new_course_material';
    public const QUIZ_AVAILABLE = 'quiz_available';
    public const QUIZ_CLOSING_SOON = 'quiz_closing_soon';
    public const COURSE_ANNOUNCEMENT = 'course_announcement';
    public const DIRECT_MESSAGE = 'direct_message';
    public const ENROLLMENT_CONFIRMED = 'enrollment_confirmed';
    public const DISCUSSION_REPLY = 'discussion_reply';
    public const LIVE_SESSION = 'live_session';

    // Student notifications - Medium
    public const PEER_REVIEW_ASSIGNED = 'peer_review_assigned';
    public const ATTENDANCE_FLAGGED = 'attendance_flagged';
    public const GROUP_ACTIVITY_UPDATE = 'group_activity_update';

    // Instructor notifications - Essential
    public const NEW_SUBMISSION = 'new_submission';
    public const SUBMISSION_DEADLINE_HIT = 'submission_deadline_hit';
    public const UNGRADED_REMINDER = 'ungraded_reminder';
    public const STUDENT_MESSAGE = 'student_message';
    public const GRADE_DISPUTE_FILED = 'grade_dispute_filed';
    public const COURSE_APPROVED = 'course_approved';
    public const COURSE_REJECTED = 'course_rejected';
    public const PLAGIARISM_ALERT = 'plagiarism_alert';
    public const DISCUSSION_FLAGGED = 'discussion_flagged';
    public const QUIZ_ATTEMPTS_DIGEST = 'quiz_attempts_digest';

    // Instructor notifications - Medium
    public const STUDENT_ENROLLED = 'student_enrolled';
    public const STUDENT_DROPPED = 'student_dropped';
    public const LOW_ENGAGEMENT_ALERT = 'low_engagement_alert';

    // Admin notifications - Essential
    public const COURSE_PENDING_REVIEW = 'course_pending_review';
    public const SYSTEM_ERROR_JOB_FAIL = 'system_error_job_fail';
    public const STORAGE_THRESHOLD_HIT = 'storage_threshold_hit';
    public const USER_REPORTED = 'user_reported';
    public const HIGH_SERVER_LOAD = 'high_server_load';
    public const NEW_SUPPORT_TICKET = 'new_support_ticket';
    public const BULK_ENROLLMENT_DONE = 'bulk_enrollment_done';

    // Admin notifications - Medium
    public const NEW_USER_REGISTERED = 'new_user_registered';
    public const AUDIT_LOG_EVENT = 'audit_log_event';
    public const SCHEDULED_MAINTENANCE = 'scheduled_maintenance';

    /**
     * Get all notification types grouped by role
     */
    public static function allByRole(): array
    {
        return [
            'student' => [
                'essential' => [
                    self::ASSIGNMENT_POSTED,
                    self::ASSIGNMENT_DUE_SOON,
                    self::ASSIGNMENT_OVERDUE,
                    self::GRADE_RELEASED,
                    self::FEEDBACK_ADDED,
                    self::NEW_COURSE_MATERIAL,
                    self::QUIZ_AVAILABLE,
                    self::QUIZ_CLOSING_SOON,
                    self::COURSE_ANNOUNCEMENT,
                    self::DIRECT_MESSAGE,
                    self::ENROLLMENT_CONFIRMED,
                    self::DISCUSSION_REPLY,
                    self::LIVE_SESSION,
                ],
                'medium' => [
                    self::PEER_REVIEW_ASSIGNED,
                    self::ATTENDANCE_FLAGGED,
                    self::GROUP_ACTIVITY_UPDATE,
                ],
            ],
            'instructor' => [
                'essential' => [
                    self::NEW_SUBMISSION,
                    self::SUBMISSION_DEADLINE_HIT,
                    self::UNGRADED_REMINDER,
                    self::STUDENT_MESSAGE,
                    self::GRADE_DISPUTE_FILED,
                    self::COURSE_APPROVED,
                    self::COURSE_REJECTED,
                    self::PLAGIARISM_ALERT,
                    self::DISCUSSION_FLAGGED,
                    self::QUIZ_ATTEMPTS_DIGEST,
                ],
                'medium' => [
                    self::STUDENT_ENROLLED,
                    self::STUDENT_DROPPED,
                    self::LOW_ENGAGEMENT_ALERT,
                ],
            ],
            'admin' => [
                'essential' => [
                    self::COURSE_PENDING_REVIEW,
                    self::SYSTEM_ERROR_JOB_FAIL,
                    self::STORAGE_THRESHOLD_HIT,
                    self::USER_REPORTED,
                    self::HIGH_SERVER_LOAD,
                    self::NEW_SUPPORT_TICKET,
                    self::BULK_ENROLLMENT_DONE,
                ],
                'medium' => [
                    self::NEW_USER_REGISTERED,
                    self::AUDIT_LOG_EVENT,
                    self::SCHEDULED_MAINTENANCE,
                ],
            ],
        ];
    }

    /**
     * Get all notification types as flat array
     */
    public static function all(): array
    {
        $byRole = self::allByRole();
        $all = [];
        foreach ($byRole as $role => $priorities) {
            foreach ($priorities as $priority => $types) {
                $all = array_merge($all, $types);
            }
        }
        return $all;
    }

    /**
     * Get default channels for a notification type
     */
    public static function getDefaultChannels(string $type): array
    {
        $defaults = [
            // Student - Essential (Email + In-app)
            self::ASSIGNMENT_POSTED => ['email', 'in_app'],
            self::ASSIGNMENT_DUE_SOON => ['email', 'push'],
            self::ASSIGNMENT_OVERDUE => ['email', 'in_app'],
            self::GRADE_RELEASED => ['email', 'in_app'],
            self::FEEDBACK_ADDED => ['in_app', 'email'],
            self::NEW_COURSE_MATERIAL => ['in_app'],
            self::QUIZ_AVAILABLE => ['email', 'push'],
            self::QUIZ_CLOSING_SOON => ['push', 'in_app'],
            self::COURSE_ANNOUNCEMENT => ['email', 'in_app'],
            self::DIRECT_MESSAGE => ['in_app', 'email'],
            self::ENROLLMENT_CONFIRMED => ['email'],
            self::DISCUSSION_REPLY => ['in_app'],
            self::LIVE_SESSION => ['email', 'in_app'],

            // Student - Medium
            self::PEER_REVIEW_ASSIGNED => ['email', 'in_app'],
            self::ATTENDANCE_FLAGGED => ['in_app', 'email'],
            self::GROUP_ACTIVITY_UPDATE => ['in_app'],

            // Instructor - Essential
            self::NEW_SUBMISSION => ['in_app'],
            self::SUBMISSION_DEADLINE_HIT => ['email', 'in_app'],
            self::UNGRADED_REMINDER => ['email', 'in_app'],
            self::STUDENT_MESSAGE => ['in_app', 'email'],
            self::GRADE_DISPUTE_FILED => ['email', 'in_app'],
            self::COURSE_APPROVED => ['email', 'in_app'],
            self::COURSE_REJECTED => ['email', 'in_app'],
            self::PLAGIARISM_ALERT => ['email', 'in_app'],
            self::DISCUSSION_FLAGGED => ['in_app'],
            self::QUIZ_ATTEMPTS_DIGEST => ['in_app'],

            // Instructor - Medium
            self::STUDENT_ENROLLED => ['in_app'],
            self::STUDENT_DROPPED => ['in_app'],
            self::LOW_ENGAGEMENT_ALERT => ['in_app'],

            // Admin - Essential
            self::COURSE_PENDING_REVIEW => ['email', 'in_app'],
            self::SYSTEM_ERROR_JOB_FAIL => ['email', 'in_app'],
            self::STORAGE_THRESHOLD_HIT => ['email'],
            self::USER_REPORTED => ['email', 'in_app'],
            self::HIGH_SERVER_LOAD => ['email', 'sms'],
            self::NEW_SUPPORT_TICKET => ['in_app', 'email'],
            self::BULK_ENROLLMENT_DONE => ['in_app'],

            // Admin - Medium
            self::NEW_USER_REGISTERED => ['in_app'],
            self::AUDIT_LOG_EVENT => ['in_app'],
            self::SCHEDULED_MAINTENANCE => ['email'],
        ];

        return $defaults[$type] ?? ['in_app'];
    }
}
