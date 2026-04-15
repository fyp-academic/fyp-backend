<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\ChoiceOption;
use App\Models\ChoiceResponse;

class ChoiceController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/choice-options
     * List all options for a choice activity, with response counts.
     */
    public function options(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $options = ChoiceOption::where('activity_id', $id)
            ->withCount('responses')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $options, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/choice-options
     * Add an option to a choice activity.
     */
    public function storeOption(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'option_text'   => 'required|string|max:500',
            'max_responses' => 'sometimes|nullable|integer|min:0',
            'sort_order'    => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $maxOrder = ChoiceOption::where('activity_id', $id)->max('sort_order') ?? -1;

        $option = ChoiceOption::create([
            'id'            => Str::uuid()->toString(),
            'activity_id'   => $id,
            'option_text'   => $request->option_text,
            'max_responses' => $request->input('max_responses'),
            'sort_order'    => $request->input('sort_order', $maxOrder + 1),
        ]);

        return response()->json(['message' => 'Option created.', 'data' => $option], 201);
    }

    /**
     * POST /api/v1/activities/{id}/choice-responses
     * Student submits their choice.
     */
    public function respond(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'option_id' => 'required|string|exists:choice_options,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $user = $request->user();

        $existing = ChoiceResponse::where('activity_id', $id)
            ->where('student_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already responded.'], 409);
        }

        $response = ChoiceResponse::create([
            'id'          => Str::uuid()->toString(),
            'activity_id' => $id,
            'option_id'   => $request->option_id,
            'student_id'  => $user->id,
        ]);

        return response()->json(['message' => 'Response recorded.', 'data' => $response], 201);
    }

    /**
     * GET /api/v1/activities/{id}/choice-results
     * Aggregated results of the choice poll.
     */
    public function results(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $options = ChoiceOption::where('activity_id', $id)
            ->withCount('responses')
            ->orderBy('sort_order')
            ->get();

        $totalResponses = $options->sum('responses_count');

        $data = $options->map(fn ($opt) => [
            'id'          => $opt->id,
            'option_text' => $opt->option_text,
            'count'       => $opt->responses_count,
            'percentage'  => $totalResponses > 0
                ? round(($opt->responses_count / $totalResponses) * 100, 1)
                : 0,
        ]);

        return response()->json(['data' => $data, 'total_responses' => $totalResponses, 'activity_id' => $id]);
    }
}
