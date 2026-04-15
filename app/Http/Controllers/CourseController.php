<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\Category;
use App\Models\Enrollment;
use App\Models\User;

class CourseController extends Controller
{
    /**
     * GET /api/v1/courses
     * Paginated list of courses with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::with(['category', 'instructor']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('instructor_id')) {
            $query->where('instructor_id', $request->instructor_id);
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('short_name', 'ilike', "%{$request->search}%");
            });
        }

        $courses = $query->orderBy('created_at', 'desc')->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $courses->items(),
            'meta' => [
                'total'        => $courses->total(),
                'per_page'     => $courses->perPage(),
                'current_page' => $courses->currentPage(),
                'last_page'    => $courses->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/courses
     * Create a new course record.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:255',
            'short_name'    => 'required|string|max:50',
            'description'   => 'sometimes|nullable|string',
            'category_id'   => 'required|string|exists:categories,id',
            'format'        => 'required|string|in:topics,weekly,social',
            'visibility'    => 'sometimes|string|in:shown,hidden',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after:start_date',
            'language'      => 'sometimes|string|max:50',
            'tags'          => 'sometimes|array',
            'max_students'  => 'sometimes|nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user     = $request->user();
        $category = Category::findOrFail($request->category_id);

        $course = Course::create([
            'id'                => Str::uuid()->toString(),
            'name'              => $request->name,
            'short_name'        => $request->short_name,
            'description'       => $request->input('description'),
            'category_id'       => $category->id,
            'category_name'     => $category->name,
            'instructor_id'     => $user->id,
            'instructor_name'   => $user->name,
            'enrolled_students' => 0,
            'status'            => 'draft',
            'visibility'        => $request->input('visibility', 'shown'),
            'format'            => $request->format,
            'start_date'        => $request->start_date,
            'end_date'          => $request->end_date,
            'language'          => $request->input('language', 'English'),
            'tags'              => $request->input('tags', []),
            'max_students'      => $request->input('max_students'),
        ]);

        $course->load(['category', 'instructor']);

        return response()->json(['message' => 'Course created.', 'data' => $course], 201);
    }

    /**
     * GET /api/v1/courses/{id}
     * Fetch full detail for a single course including sections and activities.
     */
    public function show(string $id): JsonResponse
    {
        $course = Course::with(['category', 'instructor', 'sections.activities'])->findOrFail($id);

        return response()->json(['data' => $course]);
    }

    /**
     * PUT /api/v1/courses/{id}
     * Update course metadata, status, visibility, or format.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'          => 'sometimes|string|max:255',
            'short_name'    => 'sometimes|string|max:50',
            'description'   => 'sometimes|nullable|string',
            'category_id'   => 'sometimes|string|exists:categories,id',
            'format'        => 'sometimes|string|in:topics,weekly,social',
            'status'        => 'sometimes|string|in:active,draft,archived',
            'visibility'    => 'sometimes|string|in:shown,hidden',
            'start_date'    => 'sometimes|date',
            'end_date'      => 'sometimes|date',
            'language'      => 'sometimes|string|max:50',
            'tags'          => 'sometimes|array',
            'max_students'  => 'sometimes|nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'name', 'short_name', 'description', 'category_id', 'format',
            'status', 'visibility', 'start_date', 'end_date', 'language', 'tags', 'max_students',
        ]);

        if (isset($data['category_id'])) {
            $cat = Category::findOrFail($data['category_id']);
            $data['category_name'] = $cat->name;
        }

        $course->update($data);
        $course->load(['category', 'instructor', 'sections.activities']);

        return response()->json(['message' => 'Course updated.', 'data' => $course]);
    }

    /**
     * DELETE /api/v1/courses/{id}
     * Permanently remove a course and cascade-delete related data.
     */
    public function destroy(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);
        $course->delete();

        return response()->json(['message' => 'Course deleted.']);
    }

    /**
     * GET /api/v1/courses/{id}/participants
     * Return all enrollments for a course.
     */
    public function participants(string $id): JsonResponse
    {
        $course = Course::findOrFail($id);

        $enrollments = Enrollment::where('course_id', $id)
            ->with('user')
            ->get()
            ->map(function ($e) {
                return [
                    'id'           => $e->user_id,
                    'name'         => $e->user->name ?? '',
                    'email'        => $e->user->email ?? '',
                    'role'         => $e->role,
                    'enrolledDate' => $e->enrolled_date,
                    'lastAccess'   => $e->last_access,
                    'progress'     => $e->progress,
                    'groups'       => $e->groups ?? [],
                ];
            });

        return response()->json(['data' => $enrollments, 'course_id' => $id]);
    }

    /**
     * POST /api/v1/courses/{id}/enroll
     * Enroll a user into the course with a specified role.
     */
    public function enroll(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|string|exists:users,id',
            'role'    => 'required|string|in:student,teaching_assistant',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $course = Course::findOrFail($id);

        $exists = Enrollment::where('user_id', $request->user_id)
            ->where('course_id', $id)->exists();

        if ($exists) {
            return response()->json(['message' => 'User already enrolled.'], 409);
        }

        $enrollment = Enrollment::create([
            'id'            => Str::uuid()->toString(),
            'user_id'       => $request->user_id,
            'course_id'     => $id,
            'role'          => $request->role,
            'enrolled_date' => now()->toDateString(),
            'last_access'   => now()->toDateString(),
            'progress'      => 0,
            'groups'        => [],
        ]);

        $course->increment('enrolled_students');

        return response()->json(['message' => 'User enrolled.', 'data' => $enrollment], 201);
    }

    /**
     * DELETE /api/v1/courses/{id}/enroll/{userId}
     * Remove a user's enrollment from the course.
     */
    public function unenroll(string $id, string $userId): JsonResponse
    {
        $enrollment = Enrollment::where('course_id', $id)
            ->where('user_id', $userId)->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Enrollment not found.'], 404);
        }

        $enrollment->delete();

        Course::where('id', $id)->decrement('enrolled_students');

        return response()->json(['message' => 'User unenrolled.']);
    }

    /**
     * POST /api/v1/courses/{id}/join
     * Authenticated student self-enrolls into a course.
     */
    public function selfEnroll(Request $request, string $id): JsonResponse
    {
        $user   = $request->user();
        $course = Course::findOrFail($id);

        $exists = Enrollment::where('user_id', $user->id)
            ->where('course_id', $id)->exists();

        if ($exists) {
            return response()->json(['message' => 'Already enrolled in this course.'], 409);
        }

        if ($course->max_students && $course->enrolled_students >= $course->max_students) {
            return response()->json(['message' => 'Course is full.'], 422);
        }

        Enrollment::create([
            'id'            => Str::uuid()->toString(),
            'user_id'       => $user->id,
            'course_id'     => $id,
            'role'          => 'student',
            'enrolled_date' => now()->toDateString(),
            'last_access'   => now()->toDateString(),
            'progress'      => 0,
            'groups'        => [],
        ]);

        $course->increment('enrolled_students');

        return response()->json([
            'message'   => 'Successfully enrolled in course.',
            'course_id' => $id,
            'user_id'   => $user->id,
            'role'      => 'student',
        ], 201);
    }

    /**
     * DELETE /api/v1/courses/{id}/leave
     * Authenticated student withdraws from a course.
     */
    public function selfUnenroll(Request $request, string $id): JsonResponse
    {
        $user       = $request->user();
        $enrollment = Enrollment::where('course_id', $id)
            ->where('user_id', $user->id)->first();

        if (!$enrollment) {
            return response()->json(['message' => 'Not enrolled in this course.'], 404);
        }

        $enrollment->delete();

        Course::where('id', $id)->decrement('enrolled_students');

        return response()->json([
            'message'   => 'Successfully left the course.',
            'course_id' => $id,
            'user_id'   => $user->id,
        ]);
    }
}
