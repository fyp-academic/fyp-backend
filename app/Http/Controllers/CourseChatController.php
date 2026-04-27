<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Course;
use App\Models\ConversationParticipant;
use App\Policies\RolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class CourseChatController extends Controller
{
    /**
     * GET /api/v1/courses/{courseId}/chat
     * Get or create the course chat conversation
     */
    public function getOrCreate(Request $request, string $courseId): JsonResponse
    {
        $user = Auth::user();
        $course = Course::with(['enrollments', 'instructor'])->findOrFail($courseId);

        // Check access
        if (!RolePolicy::canAccessCourse($user, $course)) {
            return response()->json(['message' => 'Forbidden. You do not have access to this course chat.'], 403);
        }

        // Find existing course chat
        $conversation = Conversation::where('type', Conversation::TYPE_COURSE)
            ->where('course_id', $courseId)
            ->first();

        if (!$conversation) {
            // Create course chat
            $conversation = Conversation::create([
                'id' => Str::uuid()->toString(),
                'type' => Conversation::TYPE_COURSE,
                'course_id' => $courseId,
                'title' => $course->name . ' Chat',
                'owner_user_id' => $course->instructor_id,
                'is_moderated' => true,
                'last_message_time' => now(),
            ]);

            // Add instructor as admin
            ConversationParticipant::create([
                'id' => Str::uuid()->toString(),
                'conversation_id' => $conversation->id,
                'user_id' => $course->instructor_id,
                'role' => 'admin',
                'joined_at' => now(),
            ]);

            // Add enrolled students as members
            $enrolledUserIds = $course->enrollments()->pluck('user_id')->unique();
            foreach ($enrolledUserIds as $studentId) {
                ConversationParticipant::firstOrCreate(
                    [
                        'conversation_id' => $conversation->id,
                        'user_id' => $studentId,
                    ],
                    [
                        'id' => Str::uuid()->toString(),
                        'role' => 'member',
                        'joined_at' => now(),
                    ]
                );
            }
        }

        return response()->json([
            'data' => $conversation->load(['participants.user', 'course']),
        ]);
    }

    /**
     * GET /api/v1/courses/{courseId}/chat/participants
     * Get all participants in the course chat
     */
    public function participants(string $courseId): JsonResponse
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);

        if (!RolePolicy::canAccessCourse($user, $course)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $conversation = Conversation::where('type', Conversation::TYPE_COURSE)
            ->where('course_id', $courseId)
            ->first();

        if (!$conversation) {
            return response()->json(['data' => []]);
        }

        $participants = $conversation->participants()
            ->with('user')
            ->where('is_blocked', false)
            ->get();

        return response()->json(['data' => $participants]);
    }

    /**
     * POST /api/v1/courses/{courseId}/chat/sync-participants
     * Sync participants when new students enroll or unenroll
     */
    public function syncParticipants(string $courseId): JsonResponse
    {
        $user = Auth::user();
        $course = Course::with('enrollments')->findOrFail($courseId);

        // Only instructor or admin can sync
        if (!RolePolicy::canManageCourse($user, $course)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $conversation = Conversation::where('type', Conversation::TYPE_COURSE)
            ->where('course_id', $courseId)
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Course chat not found.'], 404);
        }

        // Get current enrolled student IDs
        $enrolledIds = $course->enrollments()->pluck('user_id')->toArray();
        $instructorId = $course->instructor_id;

        // Add any missing participants
        foreach ($enrolledIds as $studentId) {
            ConversationParticipant::firstOrCreate(
                [
                    'conversation_id' => $conversation->id,
                    'user_id' => $studentId,
                ],
                [
                    'id' => Str::uuid()->toString(),
                    'role' => 'member',
                    'joined_at' => now(),
                ]
            );
        }

        // Ensure instructor is admin
        ConversationParticipant::firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id' => $instructorId,
            ],
            [
                'id' => Str::uuid()->toString(),
                'role' => 'admin',
                'joined_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Participants synchronized.',
            'count' => $conversation->participants()->count(),
        ]);
    }

    /**
     * POST /api/v1/courses/{courseId}/chat/announcement
     * Post an announcement to the course chat
     */
    public function postAnnouncement(Request $request, string $courseId): JsonResponse
    {
        $user = Auth::user();
        $course = Course::findOrFail($courseId);

        // Only instructor or admin can post announcements
        if (!RolePolicy::canManageCourse($user, $course)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conversation = Conversation::where('type', Conversation::TYPE_COURSE)
            ->where('course_id', $courseId)
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Course chat not found.'], 404);
        }

        $message = \App\Models\Message::create([
            'id' => Str::uuid()->toString(),
            'conversation_id' => $conversation->id,
            'sender_id' => $user->id,
            'sender_name' => $user->name,
            'content' => $request->input('content'),
            'message_type' => \App\Models\Message::TYPE_ANNOUNCEMENT,
            'timestamp' => now(),
            'read' => false,
        ]);

        $conversation->update([
            'last_message' => '📢 ' . $request->input('content'),
            'last_message_time' => now(),
        ]);

        // Broadcast to all participants
        broadcast(new \App\Events\MessageSent($message))->toOthers();

        return response()->json([
            'message' => 'Announcement posted.',
            'data' => $message,
        ], 201);
    }
}
