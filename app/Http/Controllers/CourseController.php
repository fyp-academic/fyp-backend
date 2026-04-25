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
use App\Models\DegreeProgramme;

class CourseController extends Controller
{
    /**
     * GET /api/v1/courses
     * Paginated list of courses with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Course::with(['category', 'instructor', 'sections.activities']);

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

        // Transform to ensure instructor is a string name
        $items = $courses->items();
        $transformed = array_map(function ($course) {
            $arr = $course->toArray();
            if (isset($arr['instructor']) && is_array($arr['instructor'])) {
                $arr['instructor'] = $arr['instructor']['name'] ?? $arr['instructor_name'] ?? 'Unknown';
            }
            if (!isset($arr['instructor']) || is_array($arr['instructor'])) {
                $arr['instructor'] = $arr['instructor_name'] ?? 'Unknown';
            }
            // Ensure sections is always an array
            if (!isset($arr['sections'])) {
                $arr['sections'] = [];
            }
            return $arr;
        }, $items);

        return response()->json([
            'data' => $transformed,
            'meta' => [
                'total'        => $courses->total(),
                'per_page'     => $courses->perPage(),
                'current_page' => $courses->currentPage(),
                'last_page'    => $courses->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/courses/catalog
     * Public catalog for students to browse visible courses.
     * If the user is a student with a degree programme, only show relevant courses.
     */
    public function catalog(Request $request): JsonResponse
    {
        $user = $request->user();

        // If enrolled=true, only show courses the user is enrolled in
        if ($request->boolean('enrolled') && $user) {
            $enrolledCourseIds = Enrollment::where('user_id', $user->id)
                ->pluck('course_id')
                ->toArray();

            $query = Course::with(['category', 'instructor', 'sections'])
                ->whereIn('id', $enrolledCourseIds);

            $courses = $query->orderBy('created_at', 'desc')->get();

            // Transform to ensure instructor is a string name and sections is array
            $transformed = $courses->map(function ($course) use ($user) {
                $arr = $course->toArray();
                if (isset($arr['instructor']) && is_array($arr['instructor'])) {
                    $arr['instructor'] = $arr['instructor']['name'] ?? $arr['instructor_name'] ?? 'Unknown';
                }
                if (!isset($arr['instructor']) || is_array($arr['instructor'])) {
                    $arr['instructor'] = $arr['instructor_name'] ?? 'Unknown';
                }
                if (!isset($arr['sections'])) {
                    $arr['sections'] = [];
                }
                $arr['is_enrolled'] = true; // All these are enrolled
                return $arr;
            });

            return response()->json(['data' => $transformed]);
        }

        // Otherwise, show all visible/active courses (for catalog browsing)
        $query = Course::with(['category', 'instructor', 'sections'])
            ->where('visibility', 'shown')
            ->where('status', 'active');

        // If user is a student with a degree programme, restrict to their programme's courses
        if ($user && $user->role === 'student' && $user->degree_programme_id) {
            $query->whereHas('degreeProgrammes', function ($q) use ($user) {
                $q->where('degree_programmes.id', $user->degree_programme_id);
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('degree_programme_id')) {
            $query->whereHas('degreeProgrammes', function ($q) use ($request) {
                $q->where('degree_programmes.id', $request->degree_programme_id);
            });
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->search}%")
                  ->orWhere('short_name', 'ilike', "%{$request->search}%");
            });
        }

        $courses = $query->orderBy('created_at', 'desc')->get();

        // Transform to ensure instructor is a string name and sections is array
        $transformed = $courses->map(function ($course) use ($user) {
            $arr = $course->toArray();
            if (isset($arr['instructor']) && is_array($arr['instructor'])) {
                $arr['instructor'] = $arr['instructor']['name'] ?? $arr['instructor_name'] ?? 'Unknown';
            }
            if (!isset($arr['instructor']) || is_array($arr['instructor'])) {
                $arr['instructor'] = $arr['instructor_name'] ?? 'Unknown';
            }
            if (!isset($arr['sections'])) {
                $arr['sections'] = [];
            }
            if ($user) {
                $arr['is_enrolled'] = Enrollment::where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->exists();
            }
            return $arr;
        });

        return response()->json(['data' => $transformed]);
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
            'college_id'    => 'sometimes|nullable|string|exists:colleges,id',
            'degree_programme_ids' => 'sometimes|array',
            'degree_programme_ids.*' => 'string|exists:degree_programmes,id',
            'format'        => 'required|string|in:topics,weekly,social',
            'visibility'    => 'sometimes|string|in:shown,hidden',
            'status'        => 'sometimes|string|in:draft,active,archived',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after:start_date',
            'language'      => 'sometimes|string|max:50',
            'tags'          => 'sometimes|array',
            'max_students'  => 'sometimes|nullable|integer|min:1',
            'image'         => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user     = $request->user();
        $category = Category::findOrFail($request->category_id);

        // Determine status based on visibility if not provided
        $status = $request->input('status');
        if (!$status) {
            $status = $request->input('visibility', 'shown') === 'shown' ? 'active' : 'draft';
        }

        $course = Course::create([
            'id'                => Str::uuid()->toString(),
            'name'              => $request->name,
            'short_name'        => $request->short_name,
            'description'       => $request->input('description'),
            'category_id'       => $category->id,
            'category_name'     => $category->name,
            'college_id'        => $request->input('college_id'),
            'instructor_id'     => $user->id,
            'instructor_name'   => $user->name,
            'enrolled_students' => 0,
            'status'            => $status,
            'visibility'        => $request->input('visibility', 'shown'),
            'format'            => $request->format,
            'start_date'        => $request->start_date,
            'end_date'          => $request->end_date,
            'language'          => $request->input('language', 'English'),
            'tags'              => $request->input('tags', []),
            'max_students'      => $request->input('max_students'),
            'image'             => $request->input('image'),
        ]);

        if ($request->has('degree_programme_ids')) {
            $course->degreeProgrammes()->sync($request->degree_programme_ids);
        }

        $course->load(['category', 'instructor', 'sections.activities']);

        $arr = $course->toArray();
        if (isset($arr['instructor']) && is_array($arr['instructor'])) {
            $arr['instructor'] = $arr['instructor']['name'] ?? $arr['instructor_name'] ?? 'Unknown';
        }
        if (!isset($arr['instructor']) || is_array($arr['instructor'])) {
            $arr['instructor'] = $arr['instructor_name'] ?? 'Unknown';
        }
        if (!isset($arr['sections'])) {
            $arr['sections'] = [];
        }

        return response()->json(['message' => 'Course created.', 'data' => $arr], 201);
    }

    /**
     * GET /api/v1/courses/{id}
     * Fetch full detail for a single course including sections and activities.
     */
    public function show(string $id): JsonResponse
    {
        $course = Course::with(['category', 'instructor', 'sections.activities'])->findOrFail($id);
        $arr = $course->toArray();
        if (isset($arr['instructor']) && is_array($arr['instructor'])) {
            $arr['instructor'] = $arr['instructor']['name'] ?? $arr['instructor_name'] ?? 'Unknown';
        }
        if (!isset($arr['instructor']) || is_array($arr['instructor'])) {
            $arr['instructor'] = $arr['instructor_name'] ?? 'Unknown';
        }

        return response()->json(['data' => $arr]);
    }

    /**
     * GET /api/v1/courses/{id}/public
     * Public course details for students (only visible courses).
     */
    public function publicShow(string $id): JsonResponse
    {
        $course = Course::with(['category', 'instructor', 'sections'])
            ->where('id', $id)
            ->where('visibility', 'shown')
            ->firstOrFail();
        $arr = $course->toArray();
        if (isset($arr['instructor']) && is_array($arr['instructor'])) {
            $arr['instructor'] = $arr['instructor']['name'] ?? $arr['instructor_name'] ?? 'Unknown';
        }
        if (!isset($arr['instructor']) || is_array($arr['instructor'])) {
            $arr['instructor'] = $arr['instructor_name'] ?? 'Unknown';
        }
        if (!isset($arr['sections'])) {
            $arr['sections'] = [];
        }

        return response()->json(['data' => $arr]);
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
            'name', 'short_name', 'description', 'category_id', 'college_id', 'format',
            'status', 'visibility', 'start_date', 'end_date', 'language', 'tags', 'max_students',
        ]);

        if (isset($data['category_id'])) {
            $cat = Category::findOrFail($data['category_id']);
            $data['category_name'] = $cat->name;
        }

        $course->update($data);

        if ($request->has('degree_programme_ids')) {
            $course->degreeProgrammes()->sync($request->degree_programme_ids);
        }
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
