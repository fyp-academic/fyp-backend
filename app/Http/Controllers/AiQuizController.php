<?php

namespace App\Http\Controllers;

use App\Services\AiQuizGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiQuizController extends Controller
{
    public function __construct(private AiQuizGeneratorService $generator) {}

    /**
     * POST /api/v1/courses/{courseId}/ai-quiz/generate
     *
     * Generate a draft quiz from course content — nothing is written to DB.
     * Instructor reviews/edits the returned questions before publishing.
     */
    public function generate(Request $request, string $courseId): JsonResponse
    {
        $request->validate([
            'section_id'     => 'required|string|exists:sections,id',
            'question_count' => 'sometimes|integer|min:1|max:20',
            'question_types' => 'sometimes|array',
            'question_types.*' => 'string|in:multiple_choice,true_false,short_answer',
            'difficulty'     => 'sometimes|string|in:easy,medium,hard',
        ]);

        $result = $this->generator->generate(
            courseId:      $courseId,
            sectionId:     $request->input('section_id'),
            questionCount: (int) $request->input('question_count', 5),
            questionTypes: $request->input('question_types', ['multiple_choice']),
            difficulty:    $request->input('difficulty', 'medium'),
        );

        if (empty($result['questions'])) {
            return response()->json([
                'message' => 'No questions could be generated. Ensure the section has lesson content.',
                'questions' => [],
            ], 422);
        }

        return response()->json([
            'course_id'      => $courseId,
            'section_title'  => $result['section_title'],
            'question_count' => count($result['questions']),
            'questions'      => $result['questions'],
            'source_preview' => $result['source_summary'],
        ]);
    }

    /**
     * POST /api/v1/courses/{courseId}/ai-quiz/publish
     *
     * Persist a reviewed draft as a quiz activity with questions and answers.
     */
    public function publish(Request $request, string $courseId): JsonResponse
    {
        $request->validate([
            'section_id'           => 'required|string|exists:sections,id',
            'activity_name'        => 'required|string|max:255',
            'description'          => 'sometimes|nullable|string',
            'grade_max'            => 'sometimes|numeric|min:0',
            'existing_activity_id' => 'sometimes|nullable|string|exists:activities,id',
            'questions'            => 'required|array|min:1',
            'questions.*.type'     => 'required|string|in:multiple_choice,true_false,short_answer',
            'questions.*.question_text' => 'required|string|min:3',
            'questions.*.answers'  => 'sometimes|array',
            'questions.*.answers.*.text' => 'required_with:questions.*.answers|string',
            'questions.*.answers.*.is_correct' => 'required_with:questions.*.answers|boolean',
        ]);

        $activity = $this->generator->publish(
            sectionId:          $request->input('section_id'),
            activityName:       $request->input('activity_name'),
            questions:          $request->input('questions'),
            existingActivityId: $request->input('existing_activity_id'),
            gradeMax:           (float) $request->input('grade_max', 10),
            description:        $request->input('description'),
        );

        return response()->json([
            'message'           => 'Quiz published successfully.',
            'activity_id'       => $activity->id,
            'activity_name'     => $activity->name,
            'question_count'    => $activity->quizQuestions->count(),
            'visible'           => $activity->visible,
        ], 201);
    }
}
