<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\Activity;
use App\Models\CertificateTemplate;
use App\Models\CertificateIssue;

class CertificateController extends Controller
{
    /**
     * GET /api/v1/activities/{id}/certificate
     * Get the certificate template configuration for a certificate activity.
     */
    public function show(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $template = CertificateTemplate::where('activity_id', $id)->first();

        return response()->json(['data' => $template, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/certificate
     * Create or update the certificate template.
     */
    public function upsert(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                => 'required|string|max:255',
            'body_html'           => 'sometimes|nullable|string',
            'orientation'         => 'sometimes|string|in:portrait,landscape',
            'required_activities' => 'sometimes|nullable|array',
            'min_grade'           => 'sometimes|nullable|numeric|min:0|max:100',
            'expiry_days'         => 'sometimes|nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $activity = Activity::findOrFail($id);

        $template = CertificateTemplate::updateOrCreate(
            ['activity_id' => $id],
            [
                'id'                  => Str::uuid()->toString(),
                'course_id'           => $activity->course_id,
                'name'                => $request->name,
                'body_html'           => $request->input('body_html'),
                'orientation'         => $request->input('orientation', 'landscape'),
                'required_activities' => $request->input('required_activities'),
                'min_grade'           => $request->input('min_grade'),
                'expiry_days'         => $request->input('expiry_days'),
            ]
        );

        return response()->json(['message' => 'Certificate template saved.', 'data' => $template]);
    }

    /**
     * GET /api/v1/activities/{id}/certificate/issues
     * List all issued certificates for this activity.
     */
    public function issues(string $id): JsonResponse
    {
        Activity::findOrFail($id);
        $template = CertificateTemplate::where('activity_id', $id)->first();

        if (!$template) {
            return response()->json(['data' => [], 'activity_id' => $id]);
        }

        $issues = CertificateIssue::where('certificate_id', $template->id)
            ->with('student')
            ->orderBy('issued_at', 'desc')
            ->get();

        return response()->json(['data' => $issues, 'activity_id' => $id]);
    }

    /**
     * POST /api/v1/activities/{id}/certificate/issue
     * Issue a certificate to a student.
     */
    public function issue(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|string|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        Activity::findOrFail($id);
        $template = CertificateTemplate::where('activity_id', $id)->firstOrFail();

        $existing = CertificateIssue::where('certificate_id', $template->id)
            ->where('student_id', $request->student_id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Certificate already issued.', 'data' => $existing], 409);
        }

        $issue = CertificateIssue::create([
            'id'             => Str::uuid()->toString(),
            'certificate_id' => $template->id,
            'student_id'     => $request->student_id,
            'issued_at'      => now(),
            'code'           => strtoupper(Str::random(12)),
            'expires_at'     => $template->expiry_days
                ? now()->addDays($template->expiry_days)
                : null,
        ]);

        return response()->json(['message' => 'Certificate issued.', 'data' => $issue], 201);
    }
}
