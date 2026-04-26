<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\UserPreference;

class ProfileController extends Controller
{
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
        } elseif ($user->role === 'student') {
            $profileData['degree_programme'] = $user->degreeProgramme;
            if ($user->degreeProgramme) {
                $profileData['college'] = $user->degreeProgramme->college;
            }
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
}
