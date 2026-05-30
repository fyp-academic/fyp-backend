<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\PersonalizationContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PersonalizationController extends Controller
{
    public function __construct(
        private PersonalizationContextService $contextService,
    ) {}

    /**
     * GET /api/v1/student/courses/{courseId}/personalization
     *
     * Returns content, presentation, and navigation adaptation context for a course.
     */
    public function show(string $courseId): JsonResponse
    {
        $student = Auth::user();
        if (! $student) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $context = $this->contextService->forCourse($student->id, $courseId);

        $presentationActive = (bool) ($context['presentation']['is_active'] ?? false);
        $navigationActive = ($context['navigation']['mode'] ?? 'balanced') !== 'balanced'
            || ($context['navigation']['direct_guidance']['enabled'] ?? false);

        $context['transparency'] = [
            'instructor_content_immutable' => true,
            'message' => 'Course navigation and reading layout are personalized. Lesson text is adapted only when AI delivery changes are verified.',
            'layers' => [
                'content' => 'Per-chunk; verified at request time',
                'presentation' => $presentationActive,
                'navigation' => $navigationActive,
            ],
        ];

        return response()->json(['data' => $context]);
    }
}
