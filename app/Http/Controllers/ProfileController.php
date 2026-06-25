<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\StudentProfile;
use App\Models\UserPreference;
use App\Services\BadgeService;
use App\Services\PresentationAdaptationService;
use App\Services\StudentProfileService;

class ProfileController extends Controller
{
    public function __construct(
        private BadgeService $badges,
        private StudentProfileService $profileService,
    ) {}

    /**
     * GET /api/v1/profile
     * Return the full profile for the authenticated user with related data.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        // Load related data based on role
        $user->load(['degreeProgramme.college']);

        if ($user->role === 'instructor' && $user->instructor) {
            $user->load(['instructor.college', 'instructor.degreeProgrammes']);
        }

        // Add profile image URL
        $profileData = $user->toArray();
        $profileData['profile_image_url'] = $this->getProfileImageUrl($user);

        // Add role-specific data
        if ($user->role === 'instructor' && $user->instructor) {
            $profileData['instructor_profile'] = $user->instructor;
            $profileData['assigned_degree_programmes'] = $user->instructor->degreeProgrammes;
            $profileData['college'] = $user->instructor->college;
            $profileData['college_code'] = $user->instructor->college?->code;
            $profileData['total_students'] = $user->instructor->totalStudents();
            // Bio may live on the instructor profile (set at account creation) while
            // the page reads top-level bio — surface it so "About Me" isn't blank.
            $profileData['bio'] = $user->bio ?: $user->instructor->bio;
        } elseif ($user->role === 'student') {
            $profileData['degree_programme'] = $user->degreeProgramme;
            if ($user->degreeProgramme) {
                $profileData['college'] = $user->degreeProgramme->college;
                $profileData['college_code'] = $user->degreeProgramme->college?->code;
                $profileData['program_code'] = $user->degreeProgramme->code;
            }
        }

        // Map fields to match frontend expectations
        $profileData['registration_no'] = $user->registration_number;
        $profileData['phone'] = $user->phone_number;

        // Badges/achievements — evaluate (awards any newly-earned, backfills existing users)
        // then return the earned set for the profile "achievements" list.
        if ($user->role === 'student') {
            $this->badges->evaluate($user);
            $profileData['achievements'] = $this->badges->earnedFor($user);
        }

        return response()->json(['data' => $profileData]);
    }

    /**
     * Get the full URL for the user's profile image.
     */
    private function getProfileImageUrl($user): ?string
    {
        // For instructors, check instructor profile photo first
        if ($user->role === 'instructor' && $user->instructor && $user->instructor->profile_photo) {
            return asset('storage/' . $user->instructor->profile_photo);
        }

        // Check user profile image
        if ($user->profile_image) {
            return asset('storage/' . $user->profile_image);
        }

        // Return default avatar based on role
        return null;
    }

    /**
     * PUT /api/v1/profile
     * Update editable profile fields based on user role.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $user->role;

        // Base validation rules for all roles
        $rules = [
            'name'        => 'sometimes|string|max:255',
            'bio'         => 'sometimes|nullable|string',
            'department'  => 'sometimes|nullable|string|max:255',
            'institution' => 'sometimes|nullable|string|max:255',
            'country'     => 'sometimes|nullable|string|max:100',
            'timezone'    => 'sometimes|nullable|string|max:100',
            'language'    => 'sometimes|nullable|string|max:50',
            'phone_number' => 'sometimes|nullable|string|max:20',
            'gender'      => 'sometimes|nullable|in:male,female,other',
            'nationality' => 'sometimes|nullable|string|max:100',
        ];

        // Admin can update additional fields
        if ($role === 'admin') {
            $rules['email'] = 'sometimes|string|email|max:255|unique:users,email,' . $user->id;
        }

        // Students can update education-related fields
        if ($role === 'student') {
            $rules['year_of_study'] = 'sometimes|nullable|integer|min:1|max:6';
            $rules['education_level'] = 'sometimes|nullable|string|max:100';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Fields that can be updated for all users
        $fillableFields = ['name', 'bio', 'department', 'institution', 'country', 'timezone', 'language', 'phone_number', 'gender', 'nationality'];

        if ($role === 'admin') {
            $fillableFields[] = 'email';
        }

        if ($role === 'student') {
            $fillableFields[] = 'year_of_study';
            $fillableFields[] = 'education_level';
        }

        $user->fill($request->only($fillableFields));
        $user->save();

        // If instructor, also update instructor profile if exists
        if ($role === 'instructor' && $user->instructor) {
            $this->updateInstructorProfile($request, $user->instructor);
        }

        return $this->show($request);
    }

    /**
     * Update instructor profile fields.
     */
    private function updateInstructorProfile(Request $request, $instructor): void
    {
        $instructorFields = [
            'full_name', 'gender', 'date_of_birth', 'nationality', 'phone_number',
            'national_id', 'bio', 'office_location', 'office_hours',
            'highest_qualification', 'field_of_specialization',
            'awarding_institution', 'year_of_graduation'
        ];

        $instructor->fill($request->only($instructorFields));
        $instructor->save();
    }

    /**
     * POST /api/v1/profile/image
     * Upload or update profile image.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Store the image
        $file = $request->file('image');
        $path = $file->store('profile-images', 'public');

        // Delete old image if exists
        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        // Update user with new image path
        $user->profile_image = $path;
        $user->save();

        return response()->json([
            'message' => 'Profile image uploaded successfully.',
            'data' => [
                'profile_image_url' => asset('storage/' . $path)
            ]
        ]);
    }

    /**
     * POST /api/v1/profile/instructor/image
     * Upload or update instructor profile photo.
     */
    public function uploadInstructorImage(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role !== 'instructor' || !$user->instructor) {
            return response()->json(['error' => 'Only instructors can upload instructor profile photo.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $instructor = $user->instructor;

        // Store the image
        $file = $request->file('image');
        $path = $file->store('instructor-photos', 'public');

        // Delete old image if exists
        if ($instructor->profile_photo) {
            Storage::disk('public')->delete($instructor->profile_photo);
        }

        // Update instructor with new image path
        $instructor->profile_photo = $path;
        $instructor->save();

        return response()->json([
            'message' => 'Instructor profile photo uploaded successfully.',
            'data' => [
                'profile_image_url' => asset('storage/' . $path)
            ]
        ]);
    }

    /**
     * DELETE /api/v1/profile/image
     * Remove profile image.
     */
    public function removeImage(Request $request): JsonResponse
    {
        $user = $request->user();

        // Delete image if exists
        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
            $user->profile_image = null;
            $user->save();
        }

        return response()->json(['message' => 'Profile image removed.']);
    }

    /**
     * PUT /api/v1/profile/learning-style
     * Persist the learner's declared learning style, modes, pace, interests, and support notes.
     */
    public function updateLearningStyle(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'vark_style'         => 'nullable|string|in:visual,auditory,reading,kinesthetic',
            'preferred_modes'    => 'nullable|array',
            'preferred_modes.*'  => 'string',
            'pace_preference'    => 'nullable|string|in:self-directed,guided,accelerated',
            'declared_interests' => 'nullable|array',
            'declared_interests.*' => 'string',
            'support_notes'      => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $user->fill($request->only([
            'vark_style', 'preferred_modes', 'pace_preference',
            'declared_interests', 'support_notes',
        ]));
        $user->save();

        // Refresh the behavioral profile synchronously so the change takes effect immediately
        // (an async job would leave "nothing happens" until a queue worker runs).
        $this->profileService->recalculate($user->id);

        // Persist the explicit presentation choice. Learning style is presentation-only: it
        // selects which player/layout the learner sees and overrides the instructor pin in
        // selectMode(); it never alters content adaptation depth or navigation.
        $modes = (array) ($user->preferred_modes ?? []);
        $declaredModality = null;
        if (in_array('video', $modes, true) || in_array('multimedia', $modes, true)) {
            $declaredModality = 'visual';
        } elseif (in_array('classroom', $modes, true) || in_array('live', $modes, true)) {
            $declaredModality = 'example-based';
        }
        $mode = PresentationAdaptationService::modeForStyle($declaredModality, $user->vark_style);
        if ($mode !== null) {
            $profile = StudentProfile::firstOrNew(['student_id' => $user->id]);
            if (! $profile->exists) {
                $profile->id = Str::uuid()->toString();
            }
            $profile->preferred_presentation_mode = $mode;
            $profile->save();
        }

        return response()->json([
            'message' => 'Learning style saved.',
            'data'    => [
                'vark_style'         => $user->vark_style,
                'preferred_modes'    => $user->preferred_modes,
                'pace_preference'    => $user->pace_preference,
                'declared_interests' => $user->declared_interests,
                'support_notes'      => $user->support_notes,
            ],
        ]);
    }

    /**
     * GET /api/v1/profile/preferences
     * Retrieve all preference toggles for the user.
     */
    public function preferences(Request $request): JsonResponse
    {
        $prefs = UserPreference::where('user_id', $request->user()->id)->get();

        return response()->json(['data' => $prefs, 'user_id' => $request->user()->id]);
    }

    /**
     * PUT /api/v1/profile/preferences
     * Enable or disable individual preference settings by preference_key.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'preference_key'   => 'required|string|in:email_notifications,forum_subscriptions,grading_reminders,ai_suggestions',
            'preference_value' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        $pref = UserPreference::updateOrCreate(
            ['user_id' => $user->id, 'preference_key' => $request->preference_key],
            [
                'id'      => Str::uuid()->toString(),
                'enabled' => $request->preference_value,
            ]
        );

        return response()->json(['message' => 'Preference updated.', 'data' => $pref]);
    }

    /**
     * GET /api/v1/profile/my-instructors
     * Get all instructors assigned to the student's degree programme.
     */
    public function myInstructors(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only students can access this endpoint
        if ($user->role !== 'student') {
            return response()->json(['message' => 'Forbidden. Student access only.'], 403);
        }

        // Check if student has a degree programme
        if (!$user->degree_programme_id) {
            return response()->json(['data' => [], 'message' => 'No degree programme assigned.']);
        }

        // Load the user's degree programme with instructors
        $user->load(['degreeProgramme.instructors']);

        if (!$user->degreeProgramme) {
            return response()->json(['data' => [], 'message' => 'No degree programme found.']);
        }

        $degreeProgrammeId = $user->degree_programme_id;

        $instructors = $user->degreeProgramme->instructors->map(function ($instructorUser) use ($degreeProgrammeId) {
            // Load instructor profile if exists
            $profile = $instructorUser->instructor;

            // Get courses created by this instructor that belong to the student's degree programme
            $courses = \App\Models\Course::where('instructor_id', $instructorUser->id)
                ->whereHas('degreeProgrammes', function ($query) use ($degreeProgrammeId) {
                    $query->where('degree_programmes.id', $degreeProgrammeId);
                })
                ->select('id', 'name as title', 'short_name as code', 'status', 'visibility')
                ->where('status', 'active')
                ->get()
                ->map(function ($course) {
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'code' => $course->code ?? null,
                        'status' => $course->status,
                        'visibility' => $course->visibility,
                    ];
                })
                ->toArray();

            return [
                'id' => $profile?->id ?? $instructorUser->id,
                'user_id' => $instructorUser->id,
                'name' => $instructorUser->name ?? 'Unknown',
                'email' => $instructorUser->email ?? null,
                'profile_image_url' => $this->getInstructorProfileImageUrl($profile),
                'courses' => $courses,
                'phone_number' => $profile?->phone_number ?? $instructorUser->phone_number ?? null,
                'office_hours' => $profile?->office_hours ?? null,
                'office_location' => $profile?->office_location ?? null,
                'academic_rank' => $profile?->academic_rank ?? null,
                'bio' => $profile?->bio ?? $instructorUser->bio ?? null,
            ];
        })->filter(function ($instructor) {
            // Only return instructors with valid data
            return !empty($instructor['name']) && $instructor['name'] !== 'Unknown';
        })->values();

        return response()->json(['data' => $instructors]);
    }

    /**
     * Get the profile image URL for an instructor.
     */
    private function getInstructorProfileImageUrl($instructor): ?string
    {
        if (!$instructor) {
            return null;
        }

        if ($instructor->profile_photo) {
            return asset('storage/' . $instructor->profile_photo);
        }

        if ($instructor->user && $instructor->user->profile_image) {
            return asset('storage/' . $instructor->user->profile_image);
        }

        return null;
    }
}
