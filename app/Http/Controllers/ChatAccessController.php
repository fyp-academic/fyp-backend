<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\DegreeProgramme;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatAccessController extends Controller
{
    /**
     * GET /api/v1/chat/eligible-recipients
     * Get users that the current user is allowed to chat with
     */
    public function eligibleRecipients(Request $request): JsonResponse
    {
        $user = Auth::user();

        $validator = validator($request->all(), [
            'type' => 'nullable|string|in:course,programme,direct',
            'course_id' => 'nullable|string|exists:courses,id',
            'programme_id' => 'nullable|string|exists:degree_programmes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $type = $request->input('type', 'direct');

        return match ($type) {
            'course' => $this->getCourseRecipients($user, $request->input('course_id')),
            'programme' => $this->getProgrammeRecipients($user, $request->input('programme_id')),
            default => $this->getDirectRecipients($user),
        };
    }

    /**
     * GET /api/v1/chat/my-chats
     * Get all chats the user has access to (structured by type)
     */
    public function myChats(): JsonResponse
    {
        $user = Auth::user();

        // Direct conversations
        $directChats = $user->allConversations()
            ->where('type', 'direct')
            ->with(['owner', 'participant'])
            ->orderBy('last_message_time', 'desc')
            ->get();

        // Course chats
        $courseChats = $user->allConversations()
            ->where('type', 'course')
            ->with(['course.instructor', 'participants.user'])
            ->orderBy('last_message_time', 'desc')
            ->get();

        // Programme chats
        $programmeChats = $user->allConversations()
            ->where('type', 'programme')
            ->with(['degreeProgramme', 'participants.user'])
            ->orderBy('last_message_time', 'desc')
            ->get();

        return response()->json([
            'data' => [
                'direct' => $directChats,
                'courses' => $courseChats,
                'programmes' => $programmeChats,
            ],
        ]);
    }

    /**
     * GET /api/v1/chat/available-courses
     * Get courses where user can access chat
     */
    public function availableCourses(): JsonResponse
    {
        $user = Auth::user();

        if (RolePolicy::isAdmin($user)) {
            $courses = Course::with('instructor')
                ->where('status', 'active')
                ->get();
        } elseif (RolePolicy::isInstructor($user)) {
            $assignedProgrammeIds = RolePolicy::getAssignedProgrammeIds($user);
            $courses = Course::with('instructor')
                ->where(function ($q) use ($user, $assignedProgrammeIds) {
                    $q->where('instructor_id', $user->id)
                        ->orWhereHas('degreeProgrammes', function ($subQ) use ($assignedProgrammeIds) {
                            $subQ->whereIn('degree_programmes.id', $assignedProgrammeIds);
                        });
                })
                ->where('status', 'active')
                ->get();
        } else {
            // Student - enrolled courses
            $courses = Course::with('instructor')
                ->whereHas('enrollments', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->where('status', 'active')
                ->get();
        }

        return response()->json(['data' => $courses]);
    }

    /**
     * GET /api/v1/chat/available-programmes
     * Get programmes where user can access chat
     */
    public function availableProgrammes(): JsonResponse
    {
        $user = Auth::user();

        if (RolePolicy::isAdmin($user)) {
            $programmes = DegreeProgramme::with('college')->get();
        } elseif (RolePolicy::isInstructor($user)) {
            $assignedIds = RolePolicy::getAssignedProgrammeIds($user);
            $programmes = DegreeProgramme::with('college')
                ->whereIn('id', $assignedIds)
                ->get();
        } else {
            // Student - own programme only
            $programmes = DegreeProgramme::with('college')
                ->where('id', $user->degree_programme_id)
                ->get();
        }

        return response()->json(['data' => $programmes]);
    }

    /**
     * Get direct message recipients
     */
    private function getDirectRecipients($user): JsonResponse
    {
        if (RolePolicy::isAdmin($user)) {
            // Admin can message anyone
            $recipients = User::where('id', '!=', $user->id)
                ->select('id', 'name', 'role', 'degree_programme_id')
                ->get();
        } elseif (RolePolicy::isInstructor($user)) {
            // Instructors can message:
            // - Other instructors in same programmes
            // - Students in their assigned programmes
            $assignedProgrammeIds = RolePolicy::getAssignedProgrammeIds($user);

            $recipients = User::where('id', '!=', $user->id)
                ->where(function ($q) use ($assignedProgrammeIds) {
                    // Other instructors
                    $q->where('role', 'instructor')
                        ->whereHas('assignedDegreeProgrammes', function ($subQ) use ($assignedProgrammeIds) {
                            $subQ->whereIn('degree_programmes.id', $assignedProgrammeIds);
                        });
                })
                ->orWhere(function ($q) use ($assignedProgrammeIds) {
                    // Students in same programmes
                    $q->where('role', 'student')
                        ->whereIn('degree_programme_id', $assignedProgrammeIds);
                })
                ->select('id', 'name', 'role', 'degree_programme_id')
                ->get();
        } else {
            // Students can message:
            // - Classmates (same programme)
            // - Instructors assigned to their programme
            $studentProgrammeId = $user->degree_programme_id;

            $recipients = User::where('id', '!=', $user->id)
                ->where(function ($q) use ($studentProgrammeId) {
                    // Classmates
                    $q->where('role', 'student')
                        ->where('degree_programme_id', $studentProgrammeId);
                })
                ->orWhere(function ($q) use ($studentProgrammeId) {
                    // Assigned instructors
                    $q->where('role', 'instructor')
                        ->whereHas('assignedDegreeProgrammes', function ($subQ) use ($studentProgrammeId) {
                            $subQ->where('degree_programmes.id', $studentProgrammeId);
                        });
                })
                ->select('id', 'name', 'role', 'degree_programme_id')
                ->get();
        }

        return response()->json([
            'type' => 'direct',
            'data' => $recipients,
        ]);
    }

    /**
     * Get course recipients
     */
    private function getCourseRecipients($user, ?string $courseId): JsonResponse
    {
        if (!$courseId) {
            return response()->json([
                'type' => 'course',
                'data' => [],
                'message' => 'Course ID required',
            ]);
        }

        $course = Course::with(['enrollments.user', 'instructor'])->findOrFail($courseId);

        if (!RolePolicy::canAccessCourse($user, $course)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $recipients = collect();

        // Add instructor
        if ($course->instructor) {
            $recipients->push($course->instructor);
        }

        // Add enrolled students
        foreach ($course->enrollments as $enrollment) {
            if ($enrollment->user_id !== $user->id) {
                $recipients->push($enrollment->user);
            }
        }

        return response()->json([
            'type' => 'course',
            'course_id' => $courseId,
            'data' => $recipients->unique('id')->values(),
        ]);
    }

    /**
     * Get programme recipients
     */
    private function getProgrammeRecipients($user, ?string $programmeId): JsonResponse
    {
        if (!$programmeId) {
            return response()->json([
                'type' => 'programme',
                'data' => [],
                'message' => 'Programme ID required',
            ]);
        }

        $programme = DegreeProgramme::with(['students', 'instructors'])->findOrFail($programmeId);

        // Check access
        if (!RolePolicy::isAdmin($user) &&
            !$programme->instructors()->where('instructor_id', $user->id)->exists() &&
            $user->degree_programme_id !== $programme->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $recipients = collect();

        // Add instructors
        foreach ($programme->instructors as $instructor) {
            $recipients->push($instructor);
        }

        // Add students
        foreach ($programme->students as $student) {
            if ($student->id !== $user->id) {
                $recipients->push($student);
            }
        }

        return response()->json([
            'type' => 'programme',
            'programme_id' => $programmeId,
            'data' => $recipients->unique('id')->values(),
        ]);
    }
}
