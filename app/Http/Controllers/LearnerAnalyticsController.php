<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Course;
use App\Models\LearnerProfile;
use App\Models\BehavioralSignal;
use App\Models\CognitiveSignal;
use App\Models\EmotionalSignal;
use App\Models\RiskScore;
use App\Models\Intervention;
use App\Models\FeedbackEvaluation;
use App\Models\ProfileDriftLog;

class LearnerAnalyticsController extends Controller
{
    /**
     * GET /api/v1/courses/{id}/learners/{userId}/profile
     * Return the L0 declared HATC profile, LMS flags, and drift status.
     */
    public function profile(string $id, string $userId): JsonResponse
    {
        $profile = LearnerProfile::where('course_id', $id)
            ->where('learner_id', $userId)
            ->first();

        return response()->json(['data' => $profile, 'course_id' => $id, 'user_id' => $userId]);
    }

    /**
     * POST /api/v1/courses/{id}/learners/{userId}/profile
     * Create or update a learner's HATC profile scores, preferences, and LMS flags.
     */
    public function setProfile(Request $request, string $id, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'profile_type'         => 'required|string|in:H,A,T,C,mixed',
            'h_score'              => 'sometimes|numeric|min:0|max:1',
            'a_score'              => 'sometimes|numeric|min:0|max:1',
            't_score'              => 'sometimes|numeric|min:0|max:1',
            'c_score'              => 'sometimes|numeric|min:0|max:1',
            'declared_preferences' => 'sometimes|array',
            'lms_flags'            => 'sometimes|array',
            'pulse_consent'        => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Course::findOrFail($id);

        $profile = LearnerProfile::updateOrCreate(
            ['learner_id' => $userId, 'course_id' => $id],
            array_merge(
                ['id' => Str::uuid()->toString()],
                $request->only([
                    'profile_type', 'h_score', 'a_score', 't_score', 'c_score',
                    'declared_preferences', 'lms_flags', 'pulse_consent',
                ]),
                [
                    'primary_profile' => $request->profile_type,
                    'pulse_consent_at' => $request->input('pulse_consent') ? now() : null,
                ]
            )
        );

        return response()->json(['message' => 'Learner profile saved.', 'data' => $profile]);
    }

    /**
     * GET /api/v1/courses/{id}/learners/{userId}/signals/behavioral
     * Weekly L1 behavioral data.
     */
    public function behavioralSignals(Request $request, string $id, string $userId): JsonResponse
    {
        $query = BehavioralSignal::where('course_id', $id)->where('learner_id', $userId);

        if ($request->filled('week')) {
            $query->where('week_number', $request->query('week'));
        }

        $data = $query->orderBy('week_number')->get();

        return response()->json(['data' => $data, 'course_id' => $id, 'user_id' => $userId]);
    }

    /**
     * GET /api/v1/courses/{id}/learners/{userId}/signals/cognitive
     * Weekly L2 cognitive data.
     */
    public function cognitiveSignals(Request $request, string $id, string $userId): JsonResponse
    {
        $query = CognitiveSignal::where('course_id', $id)->where('learner_id', $userId);

        if ($request->filled('week')) {
            $query->where('week_number', $request->query('week'));
        }

        $data = $query->orderBy('week_number')->get();

        return response()->json(['data' => $data, 'course_id' => $id, 'user_id' => $userId]);
    }

    /**
     * GET /api/v1/courses/{id}/learners/{userId}/signals/emotional
     * Weekly L3 emotional data.
     */
    public function emotionalSignals(Request $request, string $id, string $userId): JsonResponse
    {
        $query = EmotionalSignal::where('course_id', $id)->where('learner_id', $userId);

        if ($request->filled('week')) {
            $query->where('week_number', $request->query('week'));
        }

        $data = $query->orderBy('week_number')->get();

        return response()->json(['data' => $data, 'course_id' => $id, 'user_id' => $userId]);
    }

    /**
     * POST /api/v1/courses/{id}/learners/{userId}/pulse
     * Record a learner's weekly pulse check-in (confidence 1–5, energy 1–5).
     */
    public function submitPulse(Request $request, string $id, string $userId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'week_number'      => 'required|integer|min:1',
            'pulse_confidence' => 'required|integer|min:1|max:5',
            'pulse_energy'     => 'required|integer|min:1|max:5',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $composite = round(($request->pulse_confidence + $request->pulse_energy) / 2, 1);

        $signal = EmotionalSignal::updateOrCreate(
            ['learner_id' => $userId, 'course_id' => $id, 'week_number' => $request->week_number],
            [
                'id'                => Str::uuid()->toString(),
                'pulse_confidence'  => $request->pulse_confidence,
                'pulse_energy'      => $request->pulse_energy,
                'pulse_composite'   => $composite,
                'pulse_submitted'   => true,
                'pulse_submitted_at'=> now(),
                'computed_at'       => now(),
            ]
        );

        return response()->json(['message' => 'Pulse check-in recorded.', 'data' => $signal], 201);
    }

    /**
     * GET /api/v1/courses/{id}/learners/{userId}/risk
     * Current RE risk score (0–100), tier (0–3), anomaly flag, and signal breakdown.
     */
    public function riskScore(Request $request, string $id, string $userId): JsonResponse
    {
        $query = RiskScore::where('course_id', $id)->where('learner_id', $userId);

        if ($request->filled('week')) {
            $query->where('week_number', $request->query('week'));
        }

        $risk = $query->orderBy('week_number', 'desc')->first();

        return response()->json([
            'data'      => $risk ? [
                'final_score'      => $risk->final_score,
                'tier'             => $risk->tier,
                'anomaly_flag'     => $risk->anomaly_flag,
                'signal_breakdown' => $risk->signal_breakdown,
            ] : null,
            'course_id' => $id,
            'user_id'   => $userId,
        ]);
    }

    /**
     * GET /api/v1/courses/{id}/risk-scores
     * Paginated risk scores for all learners in a course for a given week.
     */
    public function allRiskScores(Request $request, string $id): JsonResponse
    {
        Course::findOrFail($id);

        $query = RiskScore::where('course_id', $id)->with('learner');

        if ($request->filled('week')) {
            $query->where('week_number', $request->query('week'));
        }

        $data = $query->orderBy('final_score', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data'      => $data->items(),
            'course_id' => $id,
            'meta'      => [
                'total'        => $data->total(),
                'per_page'     => $data->perPage(),
                'current_page' => $data->currentPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/courses/{id}/interventions
     * List all interventions triggered for a course.
     */
    public function interventions(Request $request, string $id): JsonResponse
    {
        Course::findOrFail($id);

        $data = Intervention::where('course_id', $id)
            ->with(['learner', 'facilitator'])
            ->orderBy('sent_at', 'desc')
            ->get();

        return response()->json(['data' => $data, 'course_id' => $id]);
    }

    /**
     * POST /api/v1/courses/{id}/interventions
     * Log a facilitator-initiated or automated intervention.
     */
    public function createIntervention(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'learner_id'   => 'required|string|exists:users,id',
            'week_number'  => 'required|integer|min:1',
            'tier'         => 'required|integer|min:1|max:3',
            'channel'      => 'required|string|in:lms_message,email,video_call,pastoral_referral',
            'template_id'  => 'required|string',
            'message_body' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Course::findOrFail($id);

        $intervention = Intervention::create([
            'id'             => Str::uuid()->toString(),
            'learner_id'     => $request->learner_id,
            'course_id'      => $id,
            'facilitator_id' => $request->user()->id,
            'week_number'    => $request->week_number,
            'tier'           => $request->tier,
            'channel'        => $request->channel,
            'template_id'    => $request->template_id,
            'message_body'   => $request->message_body,
            'sent_at'        => now(),
        ]);

        return response()->json(['message' => 'Intervention logged.', 'data' => $intervention], 201);
    }

    /**
     * GET /api/v1/interventions/{id}/evaluation
     * Retrieve the FL feedback-loop evaluation for an intervention.
     */
    public function feedbackEvaluation(string $id): JsonResponse
    {
        $intervention = Intervention::findOrFail($id);
        $evaluation   = FeedbackEvaluation::where('intervention_id', $id)->first();

        return response()->json(['data' => $evaluation, 'intervention_id' => $id]);
    }

    /**
     * POST /api/v1/interventions/{id}/evaluation
     * Record T+7/T+14 score trajectory and RE calibration notes.
     */
    public function submitEvaluation(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'evaluated_at_week'       => 'required|integer|min:1',
            'score_before'            => 'required|numeric|min:0|max:100',
            'score_at_t7'             => 'sometimes|nullable|numeric|min:0|max:100',
            'score_at_t14'            => 'sometimes|nullable|numeric|min:0|max:100',
            'outcome_label'           => 'required|string|in:recovered,partial_recovery,no_change,worsened,escalated',
            'recovery_threshold_met'  => 'required|boolean',
            'model_notes'             => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $intervention = Intervention::findOrFail($id);

        $scoreDeltaT7  = $request->score_at_t7 !== null ? $request->score_at_t7 - $request->score_before : null;
        $scoreDeltaT14 = $request->score_at_t14 !== null ? $request->score_at_t14 - $request->score_before : null;

        $evaluation = FeedbackEvaluation::updateOrCreate(
            ['intervention_id' => $id],
            [
                'id'                     => Str::uuid()->toString(),
                'learner_id'             => $intervention->learner_id,
                'course_id'              => $intervention->course_id,
                'evaluated_at_week'      => $request->evaluated_at_week,
                'score_before'           => $request->score_before,
                'score_at_t7'            => $request->score_at_t7,
                'score_at_t14'           => $request->score_at_t14,
                'score_delta_t7'         => $scoreDeltaT7,
                'score_delta_t14'        => $scoreDeltaT14,
                'outcome_label'          => $request->outcome_label,
                'recovery_threshold_met' => $request->recovery_threshold_met,
                'model_notes'            => $request->input('model_notes'),
            ]
        );

        $intervention->update([
            'score_at_t7'  => $request->score_at_t7,
            'score_at_t14' => $request->score_at_t14,
            'outcome'      => $request->outcome_label,
        ]);

        return response()->json(['message' => 'Evaluation submitted.', 'data' => $evaluation], 201);
    }

    /**
     * GET /api/v1/courses/{id}/learners/{userId}/drift-logs
     * Return the profile drift detection history for a learner.
     */
    public function driftLogs(string $id, string $userId): JsonResponse
    {
        $data = ProfileDriftLog::where('course_id', $id)
            ->where('learner_id', $userId)
            ->orderBy('detected_at_week')
            ->get();

        return response()->json(['data' => $data, 'course_id' => $id, 'user_id' => $userId]);
    }
}
