<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Message;
use App\Models\Conversation;

class ChatPolicy
{
    /**
     * Deletion time window in minutes for "delete for everyone"
     */
    public const DELETE_WINDOW_MINUTES = 10;

    /**
     * Check if user can send a message in the conversation
     */
    public static function canSendMessage(User $user, Conversation $conversation): bool
    {
        // Admin can send anywhere
        if (RolePolicy::isAdmin($user)) {
            return true;
        }

        // Check if conversation is locked
        if ($conversation->is_locked) {
            // Only moderators can send when locked
            return $conversation->isModerator($user->id);
        }

        // Check conversation type access
        return match ($conversation->type) {
            Conversation::TYPE_DIRECT => self::canAccessDirectChat($user, $conversation),
            Conversation::TYPE_COURSE => self::canAccessCourseChat($user, $conversation),
            Conversation::TYPE_PROGRAMME => self::canAccessProgrammeChat($user, $conversation),
            default => false,
        };
    }

    /**
     * Check if user can delete their own message
     */
    public static function canDeleteOwnMessage(User $user, Message $message): bool
    {
        // Must be the sender
        if ($message->sender_id !== $user->id) {
            return false;
        }

        // Check time window
        return $message->isWithinDeletionWindow(self::DELETE_WINDOW_MINUTES);
    }

    /**
     * Check if user can delete a message "for everyone"
     */
    public static function canDeleteForEveryone(User $user, Message $message, Conversation $conversation): bool
    {
        // Admin can always delete
        if (RolePolicy::isAdmin($user)) {
            return true;
        }

        // Message sender can delete within time window
        if ($message->sender_id === $user->id && $message->isWithinDeletionWindow(self::DELETE_WINDOW_MINUTES)) {
            return true;
        }

        // Instructors/moderators can delete inappropriate messages in their courses/programmes
        if ($conversation->isModerator($user->id)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can delete message "for me" (hide for themselves)
     */
    public static function canDeleteForMe(User $user, Message $message, Conversation $conversation): bool
    {
        // User must be a participant
        return $conversation->hasParticipant($user->id);
    }

    /**
     * Check if user can restore a deleted message
     */
    public static function canRestoreMessage(User $user, Message $message, Conversation $conversation): bool
    {
        // Only admin can restore messages
        return RolePolicy::isAdmin($user);
    }

    /**
     * Check if user can pin/unpin messages
     */
    public static function canPinMessage(User $user, Conversation $conversation): bool
    {
        // Admin or conversation moderator
        if (RolePolicy::isAdmin($user) || $conversation->isModerator($user->id)) {
            return true;
        }

        // Instructors in course chats
        if ($conversation->isCourseChat()) {
            $course = $conversation->course;
            return $course && $course->instructor_id === $user->id;
        }

        return false;
    }

    /**
     * Check if user can block another user in a conversation
     */
    public static function canBlockUser(User $user, User $targetUser, Conversation $conversation): bool
    {
        // Cannot block yourself
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Admin can block anyone
        if (RolePolicy::isAdmin($user)) {
            return true;
        }

        // Moderators can block in their conversations
        if ($conversation->isModerator($user->id)) {
            return true;
        }

        // Instructors can block students in their courses
        if ($conversation->isCourseChat()) {
            $course = $conversation->course;
            if ($course && $course->instructor_id === $user->id) {
                return $targetUser->role === 'student';
            }
        }

        return false;
    }

    /**
     * Check if user can report a message
     */
    public static function canReportMessage(User $user, Message $message, Conversation $conversation): bool
    {
        // Cannot report your own messages
        if ($message->sender_id === $user->id) {
            return false;
        }

        // Must be a participant
        return $conversation->hasParticipant($user->id);
    }

    /**
     * Check if user can moderate (delete others' messages, block users)
     */
    public static function canModerate(User $user, Conversation $conversation): bool
    {
        // Admin can moderate anywhere
        if (RolePolicy::isAdmin($user)) {
            return true;
        }

        // Check if user is a moderator in this conversation
        if ($conversation->isModerator($user->id)) {
            return true;
        }

        // Instructors in their course chats
        if ($conversation->isCourseChat()) {
            $course = $conversation->course;
            return $course && $course->instructor_id === $user->id;
        }

        // Instructors in their assigned programme chats
        if ($conversation->isProgrammeChat()) {
            $programme = $conversation->degreeProgramme;
            return $programme && $programme->instructors()->where('instructor_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Check if user can view deleted message content (for audit)
     */
    public static function canViewDeletedContent(User $user, Conversation $conversation): bool
    {
        return RolePolicy::isAdmin($user);
    }

    /**
     * Check direct chat access
     */
    private static function canAccessDirectChat(User $user, Conversation $conversation): bool
    {
        return $conversation->owner_user_id === $user->id
            || $conversation->participant_user_id === $user->id;
    }

    /**
     * Check course chat access
     */
    private static function canAccessCourseChat(User $user, Conversation $conversation): bool
    {
        $course = $conversation->course;

        if (!$course) {
            return false;
        }

        // Instructor of the course
        if ($course->instructor_id === $user->id) {
            return true;
        }

        // Enrolled students
        return $course->enrollments()->where('user_id', $user->id)->exists();
    }

    /**
     * Check programme chat access
     */
    private static function canAccessProgrammeChat(User $user, Conversation $conversation): bool
    {
        $programme = $conversation->degreeProgramme;

        if (!$programme) {
            return false;
        }

        // Assigned instructors
        if ($programme->instructors()->where('instructor_id', $user->id)->exists()) {
            return true;
        }

        // Students in the programme
        if ($user->degree_programme_id === $programme->id) {
            return true;
        }

        return false;
    }
}
