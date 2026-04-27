<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\DegreeProgramme;
use App\Models\ConversationParticipant;
use App\Policies\RolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class ProgrammeChatController extends Controller
{
    /**
     * GET /api/v1/degree-programmes/{programmeId}/chat
     * Get or create the programme chat conversation
     */
    public function getOrCreate(Request $request, string $programmeId): JsonResponse
    {
        $user = Auth::user();
        $programme = DegreeProgramme::with(['students', 'instructors'])->findOrFail($programmeId);

        // Check access
        if (!$this->canAccessProgrammeChat($user, $programme)) {
            return response()->json(['message' => 'Forbidden. You do not have access to this programme chat.'], 403);
        }

        // Find existing programme chat
        $conversation = Conversation::where('type', Conversation::TYPE_PROGRAMME)
            ->where('programme_id', $programmeId)
            ->first();

        if (!$conversation) {
            // Create programme chat
            $conversation = Conversation::create([
                'id' => Str::uuid()->toString(),
                'type' => Conversation::TYPE_PROGRAMME,
                'programme_id' => $programmeId,
                'title' => $programme->name . ' Discussion',
                'owner_user_id' => $user->id,
                'is_moderated' => true,
                'last_message_time' => now(),
            ]);

            // Add instructors as admins
            $instructorIds = $programme->instructors()->pluck('instructor_id')->unique();
            foreach ($instructorIds as $instructorId) {
                ConversationParticipant::create([
                    'id' => Str::uuid()->toString(),
                    'conversation_id' => $conversation->id,
                    'user_id' => $instructorId,
                    'role' => 'admin',
                    'joined_at' => now(),
                ]);
            }

            // Add students as members
            $studentIds = $programme->students()->pluck('id')->unique();
            foreach ($studentIds as $studentId) {
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
            'data' => $conversation->load(['participants.user', 'degreeProgramme']),
        ]);
    }

    /**
     * GET /api/v1/degree-programmes/{programmeId}/chat/participants
     * Get all participants in the programme chat
     */
    public function participants(string $programmeId): JsonResponse
    {
        $user = Auth::user();
        $programme = DegreeProgramme::findOrFail($programmeId);

        if (!$this->canAccessProgrammeChat($user, $programme)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $conversation = Conversation::where('type', Conversation::TYPE_PROGRAMME)
            ->where('programme_id', $programmeId)
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
     * POST /api/v1/degree-programmes/{programmeId}/chat/sync-participants
     * Sync participants when programme changes
     */
    public function syncParticipants(string $programmeId): JsonResponse
    {
        $user = Auth::user();
        $programme = DegreeProgramme::with(['students', 'instructors'])->findOrFail($programmeId);

        // Only admin or assigned instructor can sync
        if (!RolePolicy::isAdmin($user) && !$programme->instructors()->where('instructor_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $conversation = Conversation::where('type', Conversation::TYPE_PROGRAMME)
            ->where('programme_id', $programmeId)
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Programme chat not found.'], 404);
        }

        // Get current student and instructor IDs
        $studentIds = $programme->students()->pluck('id')->toArray();
        $instructorIds = $programme->instructors()->pluck('instructor_id')->toArray();

        // Add missing students
        foreach ($studentIds as $studentId) {
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

        // Add/ensure instructors are admins
        foreach ($instructorIds as $instructorId) {
            $participant = ConversationParticipant::firstOrCreate(
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

            // Ensure role is admin
            if ($participant->role !== 'admin') {
                $participant->update(['role' => 'admin']);
            }
        }

        return response()->json([
            'message' => 'Participants synchronized.',
            'count' => $conversation->participants()->count(),
        ]);
    }

    /**
     * POST /api/v1/degree-programmes/{programmeId}/chat/announcement
     * Post an announcement to the programme chat
     */
    public function postAnnouncement(Request $request, string $programmeId): JsonResponse
    {
        $user = Auth::user();
        $programme = DegreeProgramme::findOrFail($programmeId);

        // Only admin or assigned instructor can post announcements
        if (!RolePolicy::isAdmin($user) && !$programme->instructors()->where('instructor_id', $user->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $conversation = Conversation::where('type', Conversation::TYPE_PROGRAMME)
            ->where('programme_id', $programmeId)
            ->first();

        if (!$conversation) {
            return response()->json(['message' => 'Programme chat not found.'], 404);
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

    /**
     * Check if user can access the programme chat
     */
    private function canAccessProgrammeChat($user, DegreeProgramme $programme): bool
    {
        // Admin can access any
        if (RolePolicy::isAdmin($user)) {
            return true;
        }

        // Instructors assigned to the programme
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
