<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ContentChunk;
use App\Models\LessonPage;
use App\Models\QuizAnswer;
use App\Models\QuizQuestion;
use App\Models\Section;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiQuizGeneratorService
{
    private const ALLOWED_TYPES = ['multiple_choice', 'true_false', 'short_answer'];

    /**
     * Generate a draft quiz from course content using Gemini.
     * Returns a structured array of questions — nothing is written to the DB yet.
     *
     * @param  string[]  $questionTypes
     * @return array{questions: list<array<string,mixed>>, source_summary: string, section_title: string}
     */
    public function generate(
        string $courseId,
        string $sectionId,
        int $questionCount,
        array $questionTypes,
        string $difficulty,
    ): array {
        $questionCount = max(1, min(20, $questionCount));
        $questionTypes = array_values(array_intersect($questionTypes, self::ALLOWED_TYPES))
            ?: ['multiple_choice'];

        [$sourceText, $sectionTitle, $courseTitle] = $this->gatherSourceMaterial($courseId, $sectionId);

        $apiKey  = (string) (config('services.gemini.api_key') ?? '');
        $model   = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
        $baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');

        if ($apiKey === '' || trim($sourceText) === '') {
            return ['questions' => [], 'source_summary' => '', 'section_title' => $sectionTitle];
        }

        $prompt = $this->buildPrompt(
            $courseTitle, $sectionTitle, $sourceText,
            $questionCount, $questionTypes, $difficulty,
        );

        try {
            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$baseUrl}/models/{$model}:generateContent?key={$apiKey}", [
                    'contents'         => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.4, 'maxOutputTokens' => 4096],
                    'systemInstruction'=> ['parts' => [['text' => 'You are an expert instructional designer. Return valid JSON only — no markdown fences, no preamble.']]],
                ]);

            if (! $response->successful()) {
                Log::warning('AiQuizGeneratorService: Gemini request failed', ['status' => $response->status()]);
                return ['questions' => [], 'source_summary' => '', 'section_title' => $sectionTitle];
            }

            $raw      = (string) $response->json('candidates.0.content.parts.0.text', '');
            $raw      = trim(preg_replace('/```json|```/', '', $raw) ?? '');
            $parsed   = json_decode($raw, true);
            $questions = is_array($parsed['questions'] ?? null) ? $parsed['questions'] : (is_array($parsed) ? $parsed : []);
            $questions = $this->normaliseQuestions($questions, $courseId);

            return [
                'questions'      => $questions,
                'source_summary' => mb_substr($sourceText, 0, 300) . (mb_strlen($sourceText) > 300 ? '…' : ''),
                'section_title'  => $sectionTitle,
            ];
        } catch (\Throwable $e) {
            Log::warning('AiQuizGeneratorService: exception', ['error' => $e->getMessage()]);
            return ['questions' => [], 'source_summary' => '', 'section_title' => $sectionTitle];
        }
    }

    /**
     * Persist a reviewed draft as a new quiz activity with all questions and answers.
     *
     * @param  list<array<string,mixed>>  $questions
     */
    public function publish(
        string $sectionId,
        string $activityName,
        array $questions,
        ?string $existingActivityId,
        float $gradeMax,
        ?string $description,
    ): Activity {
        $section = Section::findOrFail($sectionId);

        if ($existingActivityId) {
            $activity = Activity::findOrFail($existingActivityId);
        } else {
            $maxOrder = Activity::where('section_id', $sectionId)->max('sort_order') ?? -1;
            $activity = Activity::create([
                'id'                => Str::uuid()->toString(),
                'section_id'        => $sectionId,
                'course_id'         => $section->course_id,
                'type'              => 'quiz',
                'name'              => $activityName,
                'description'       => $description,
                'visible'           => false,
                'completion_status' => 'none',
                'grade_max'         => $gradeMax,
                'sort_order'        => $maxOrder + 1,
            ]);
        }

        foreach ($questions as $index => $q) {
            $type = in_array($q['type'] ?? '', self::ALLOWED_TYPES, true)
                ? $q['type']
                : 'multiple_choice';

            $question = QuizQuestion::create([
                'id'              => Str::uuid()->toString(),
                'activity_id'     => $activity->id,
                'course_id'       => $section->course_id,
                'type'            => $type,
                'question_text'   => (string) ($q['question_text'] ?? ''),
                'category'        => (string) ($q['bloom_level'] ?? 'understand'),
                'default_mark'    => 1,
                'shuffle_answers' => true,
                'correct_answer'  => isset($q['correct_answer']) ? (string) $q['correct_answer'] : null,
                'is_active'       => true,
            ]);

            if ($type === 'short_answer') {
                // correct_answer already stored on the question; no answer rows needed
                continue;
            }

            foreach ((array) ($q['answers'] ?? []) as $sortIdx => $a) {
                $isCorrect = (bool) ($a['is_correct'] ?? false);
                QuizAnswer::create([
                    'id'             => Str::uuid()->toString(),
                    'question_id'    => $question->id,
                    'text'           => (string) ($a['text'] ?? ''),
                    'grade_fraction' => $isCorrect ? '1.0000' : '0.0000',
                    'feedback'       => (string) ($a['feedback'] ?? ''),
                    'sort_order'     => $sortIdx,
                ]);
            }
        }

        $activity->load('questions.answers');
        return $activity;
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /** @return array{0: string, 1: string, 2: string} [text, sectionTitle, courseTitle] */
    private function gatherSourceMaterial(string $courseId, string $sectionId): array
    {
        $section = Section::find($sectionId);
        $sectionTitle = $section?->title ?? 'Section';
        $sectionSummary = (string) ($section?->summary ?? '');

        $courseTitle = (string) (DB::table('courses')->where('id', $courseId)->value('name') ?? '');

        // Gather lesson-page content chunks from activities in this section
        $activityIds = DB::table('activities')
            ->where('section_id', $sectionId)
            ->where('type', 'lesson')
            ->pluck('id');

        $pageIds = DB::table('lesson_pages')
            ->whereIn('activity_id', $activityIds)
            ->pluck('id');

        // Prefer chunks with semantic_role = concept/example/summary; fall back to any
        $chunks = ContentChunk::whereIn('content_id', $pageIds)
            ->where('content_source', 'lesson_page')
            ->whereIn('semantic_role', ['concept', 'example', 'summary', 'introduction'])
            ->orderBy('lesson_position_pct')
            ->get(['chunk_text', 'key_terms']);

        if ($chunks->isEmpty()) {
            $chunks = ContentChunk::whereIn('content_id', $pageIds)
                ->where('content_source', 'lesson_page')
                ->orderBy('chunk_index')
                ->get(['chunk_text', 'key_terms']);
        }

        // Fall back to raw page content if no chunks exist yet
        if ($chunks->isEmpty() && $pageIds->isNotEmpty()) {
            $rawContent = LessonPage::whereIn('id', $pageIds)
                ->get(['content', 'ai_context_summary'])
                ->map(fn ($p) => $p->ai_context_summary ?? strip_tags($p->content ?? ''))
                ->implode("\n\n");

            return [mb_substr($rawContent, 0, 5000), $sectionTitle, $courseTitle];
        }

        $chunkText = $chunks
            ->map(fn ($c) => $c->chunk_text)
            ->implode("\n\n");

        $allTerms = $chunks
            ->flatMap(fn ($c) => $c->key_terms ?? [])
            ->unique()
            ->values()
            ->implode(', ');

        $source = trim(($sectionSummary ? "{$sectionSummary}\n\n" : '') . $chunkText);
        if ($allTerms !== '') {
            $source .= "\n\nKey terms: {$allTerms}";
        }

        return [mb_substr($source, 0, 5000), $sectionTitle, $courseTitle];
    }

    /**
     * @param  string[]  $questionTypes
     */
    private function buildPrompt(
        string $courseTitle,
        string $sectionTitle,
        string $sourceText,
        int $questionCount,
        array $questionTypes,
        string $difficulty,
    ): string {
        $typesStr = implode(', ', $questionTypes);
        $bloomMap = match ($difficulty) {
            'easy'  => 'remember and understand',
            'hard'  => 'analyze and evaluate',
            default => 'understand and apply',
        };

        $typeInstructions = '';
        if (in_array('multiple_choice', $questionTypes, true)) {
            $typeInstructions .= "\n- multiple_choice: provide exactly 4 answers, exactly 1 must have is_correct=true, the rest is_correct=false.";
        }
        if (in_array('true_false', $questionTypes, true)) {
            $typeInstructions .= "\n- true_false: provide exactly 2 answers with text \"True\" and \"False\", one is_correct=true.";
        }
        if (in_array('short_answer', $questionTypes, true)) {
            $typeInstructions .= "\n- short_answer: set correct_answer to a short model answer string; omit the answers array.";
        }

        return <<<PROMPT
Generate exactly {$questionCount} quiz questions for the following course section.

COURSE: {$courseTitle}
SECTION: {$sectionTitle}
DIFFICULTY: {$difficulty} (target Bloom's levels: {$bloomMap})
QUESTION TYPES ALLOWED: {$typesStr}
{$typeInstructions}

SOURCE CONTENT:
{$sourceText}

Return a JSON object in this exact shape — no markdown, no extra keys:
{
  "questions": [
    {
      "type": "multiple_choice" | "true_false" | "short_answer",
      "question_text": "<clear, unambiguous question>",
      "bloom_level": "remember|understand|apply|analyze|evaluate|create",
      "difficulty": "easy|medium|hard",
      "explanation": "<brief explanation of the correct answer for instructor reference>",
      "correct_answer": "<model answer — required for short_answer, omit for MC/TF>",
      "answers": [
        {"text": "<option>", "is_correct": true|false, "feedback": "<brief feedback>"}
      ]
    }
  ]
}

Rules:
1. Questions must be directly answerable from the SOURCE CONTENT above.
2. Do NOT invent facts not present in the source.
3. Distractors (wrong answers) must be plausible but clearly wrong to a student who studied the material.
4. question_text must be a complete sentence ending with a question mark or a clear stem.
5. Vary question types proportionally across the list.
6. Return ONLY the JSON object. No preamble, no commentary.
PROMPT;
    }

    /**
     * Normalise and sanitise Gemini's output into a consistent shape.
     *
     * @param  list<mixed>  $raw
     * @return list<array<string,mixed>>
     */
    private function normaliseQuestions(array $raw, string $courseId): array
    {
        $out = [];
        foreach ($raw as $q) {
            if (! is_array($q) || empty($q['question_text'])) {
                continue;
            }
            $type = in_array($q['type'] ?? '', self::ALLOWED_TYPES, true)
                ? $q['type']
                : 'multiple_choice';

            $answers = [];
            if ($type !== 'short_answer') {
                foreach ((array) ($q['answers'] ?? []) as $a) {
                    if (! is_array($a)) {
                        continue;
                    }
                    $answers[] = [
                        'text'       => (string) ($a['text'] ?? ''),
                        'is_correct' => (bool) ($a['is_correct'] ?? false),
                        'feedback'   => (string) ($a['feedback'] ?? ''),
                    ];
                }
            }

            $out[] = [
                'type'          => $type,
                'question_text' => (string) $q['question_text'],
                'bloom_level'   => (string) ($q['bloom_level'] ?? 'understand'),
                'difficulty'    => (string) ($q['difficulty'] ?? 'medium'),
                'explanation'   => (string) ($q['explanation'] ?? ''),
                'correct_answer'=> isset($q['correct_answer']) ? (string) $q['correct_answer'] : null,
                'answers'       => $answers,
            ];
        }
        return $out;
    }
}
