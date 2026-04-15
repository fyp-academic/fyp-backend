<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\UserPreference;

class ProfileController extends Controller
{
    /**
     * GET /api/v1/profile
     * Return the full profile for the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }

    /**
     * PUT /api/v1/profile
     * Update editable profile fields: name, bio, department, institution, country, timezone, language.
     */
    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:255',
            'bio'         => 'sometimes|nullable|string',
            'department'  => 'sometimes|nullable|string|max:255',
            'institution' => 'sometimes|nullable|string|max:255',
            'country'     => 'sometimes|nullable|string|max:100',
            'timezone'    => 'sometimes|nullable|string|max:100',
            'language'    => 'sometimes|nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = $request->user();
        $user->fill($request->only(['name', 'bio', 'department', 'institution', 'country', 'timezone', 'language']));
        $user->save();

        return response()->json(['message' => 'Profile updated.', 'data' => $user]);
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
