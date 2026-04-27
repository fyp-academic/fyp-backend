<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Conversation;

class ChatAccess
{
    /**
     * Handle an incoming request to verify chat access permissions.
     *
     * Enforces role-based access control for conversations:
     * - Direct chats: participants only
     * - Course chats: enrolled students + assigned instructors
     * - Programme chats: students in programme + assigned instructors
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Get conversation ID from route parameter
        $conversationId = $request->route('id') ?? $request->route('conversation');

        if (!$conversationId) {
            // No specific conversation requested, allow (e.g., listing all conversations)
            return $next($request);
        }

        $conversation = Conversation::with(['course', 'degreeProgramme'])->find($conversationId);

        if (!$conversation) {
            return response()->json(['message' => 'Conversation not found.'], 404);
        }

        // Admin has full access to all conversations
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Check access based on conversation type
        $hasAccess = match ($conversation->type) {
            Conversation::TYPE_DIRECT => $this->canAccessDirectChat($user, $conversation),
            Conversation::TYPE_COURSE => $this->canAccessCourseChat($user, $conversation),
            Conversation::TYPE_PROGRAMME => $this->canAccessProgrammeChat($user, $conversation),
            default => false,
        };

        if (!$hasAccess) {
            return response()->json([
                'message' => 'Forbidden. You do not have access to this conversation.',
            ], 403);
        }

        // Check if user is blocked in this conversation
        if ($this->isBlocked($user, $conversation)) {
            return response()->json([
                'message' => 'Forbidden. You have been blocked from this conversation.',
            ], 403);
        }

        // Attach conversation to request for downstream use
        $request->attributes->set('conversation', $conversation);

        return $next($request);
    }

    /**
     * Check if user can access a direct message conversation
     */
    private function canAccessDirectChat($user, Conversation $conversation): bool
    {
        // User must be owner or participant
        return $conversation->owner_user_id === $user->id
            || $conversation->participant_user_id === $user->id;
    }

    /**
     * Check if user can access a course chat
     */
    private function canAccessCourseChat($user, Conversation $conversation): bool
    {
        $course = $conversation->course;

        if (!$course) {
            return false;
        }

        // Instructor of the course can access
        if ($course->instructor_id === $user->id) {
            return true;
        }

        // Check if user is enrolled in the course
        return $course->enrollments()->where('user_id', $user->id)->exists();
    }

    /**
     * Check if user can access a programme chat
     */
    private function canAccessProgrammeChat($user, Conversation $conversation): bool
    {
        $programme = $conversation->degreeProgramme;

        if (!$programme) {
            return false;
        }

        // Instructors assigned to the programme can access
        if ($programme->instructors()->where('instructor_id', $user->id)->exists()) {
            return true;
        }

        // Students in the same programme can access
        if ($user->degree_programme_id === $programme->id) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is blocked in the conversation
     */
    private function isBlocked($user, Conversation $conversation): bool
    {
        return $conversation->participants()
            ->where('user_id', $user->id)
            ->where('is_blocked', true)
            ->exists();
    }
}
