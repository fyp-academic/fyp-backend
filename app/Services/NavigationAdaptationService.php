<?php

namespace App\Services;

use App\Models\BehavioralSignal;
use App\Models\LearnerProfile;
use App\Models\UserActivityCompletion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Navigation-level adaptation: direct guidance, adaptive ordering, link hiding, annotations.
 */
class NavigationAdaptationService
{
    /**
     * @param  array<string, mixed>  $contentProfile
     * @return array<string, mixed>
     */
    public function resolve(
        string $studentId,
        string $courseId,
        array $contentProfile,
        ?LearnerProfile $learnerProfile,
        ?BehavioralSignal $behavioral,
        array $weakTopics,
    ): array {
        $lmsFlags = $learnerProfile?->lms_flags ?? [];
        $openNavigation = (bool) ($lmsFlags['open_navigation'] ?? false);
        $structuredPathway = (bool) ($lmsFlags['structured_pathway'] ?? false);

        if (! $openNavigation && ! $structuredPathway) {
            $primary = $learnerProfile?->primary_profile;
            $openNavigation = $primary === 'H';
            $structuredPathway = in_array($primary, ['T', 'C'], true);
        }

        $navigationPattern = $behavioral?->navigation_pattern ?? 'linear';
        $enforceSequence = $structuredPathway && ! $openNavigation;
        $allowNonLinear = $openNavigation || $navigationPattern === 'random';

        $activities = $this->orderedActivities($courseId);
        $completedIds = UserActivityCompletion::where('user_id', $studentId)
            ->where('completed', true)
            ->pluck('activity_id')
            ->flip()
            ->all();

        $activityOverlays = [];
        $firstIncompleteId = null;

        foreach ($activities as $index => $activity) {
            $activityId = $activity['id'];
            $sectionTitle = $activity['section_title'];
            $isCompleted = isset($completedIds[$activityId]);
            $isWeakTopic = $this->sectionMatchesWeakTopic($sectionTitle, $weakTopics);

            if (! $isCompleted && $firstIncompleteId === null) {
                $firstIncompleteId = $activityId;
            }

            $accessible = true;
            $annotation = null;
            $annotationLabel = null;

            if ($enforceSequence) {
                $previousIncomplete = false;
                for ($i = 0; $i < $index; $i++) {
                    if (! isset($completedIds[$activities[$i]['id']])) {
                        $previousIncomplete = true;
                        break;
                    }
                }
                $accessible = ! $previousIncomplete;
                if (! $accessible) {
                    $annotation = 'locked';
                    $annotationLabel = 'Complete previous activities first';
                }
            }

            if ($activityId === $firstIncompleteId && ! $isCompleted) {
                $annotation = 'recommended';
                $annotationLabel = 'Recommended next';
            }

            if ($isWeakTopic) {
                $annotation = $annotation ?? 'weak_topic';
                $annotationLabel = $annotationLabel ?? 'Needs review';
            }

            if ($navigationPattern === 'revisit_heavy' && $isCompleted && $isWeakTopic) {
                $annotation = 'review';
                $annotationLabel = 'Review suggested';
            }

            $activityOverlays[$activityId] = [
                'accessible' => $accessible,
                'hidden' => false,
                'annotation' => $annotation,
                'annotation_label' => $annotationLabel,
                'sort_boost' => $isWeakTopic ? 10 : ($annotation === 'recommended' ? 5 : 0),
                'is_weak_topic' => $isWeakTopic,
            ];
        }

        $sectionOverlays = [];
        $sections = DB::table('sections')
            ->where('course_id', $courseId)
            ->orderBy('sort_order')
            ->get(['id', 'title']);

        foreach ($sections as $section) {
            $isWeak = $this->sectionMatchesWeakTopic($section->title, $weakTopics);
            $sectionOverlays[$section->id] = [
                'is_weak_topic' => $isWeak,
                'sort_boost' => $isWeak ? 8 : 0,
                'annotation_label' => $isWeak ? 'Priority review' : null,
            ];
        }

        $suggestedId = $firstIncompleteId;

        // Build static fallback guidance message
        $fallbackMessage = null;
        if ($suggestedId) {
            $suggested = collect($activities)->firstWhere('id', $suggestedId);
            if ($suggested && ($contentProfile['at_risk'] ?? false)) {
                $fallbackMessage = 'Your learning profile suggests continuing with "'.$suggested['title'].'" to stay on track.';
            } elseif ($suggested && ! empty($weakTopics)) {
                $fallbackMessage = 'Focus on "'.$suggested['section_title'].'" — this area needs strengthening based on your quiz results.';
            } elseif ($suggested) {
                $fallbackMessage = 'Continue with "'.$suggested['title'].'" to maintain your learning pathway.';
            }
        }

        $aiGuidance = $this->generateAiGuidance(
            $studentId,
            $courseId,
            $contentProfile,
            $activities,
            $completedIds,
            $weakTopics,
        );

        return [
            'mode' => $openNavigation ? 'open' : ($structuredPathway ? 'structured' : 'balanced'),
            'allow_non_linear_jump' => $allowNonLinear,
            'enforce_sequence' => $enforceSequence,
            'navigation_pattern' => $navigationPattern,
            'direct_guidance' => [
                'enabled'               => ($aiGuidance['pathway_message'] ?? $fallbackMessage) !== null,
                'message'               => $aiGuidance['pathway_message'] ?? $fallbackMessage,
                'suggested_activity_id' => $aiGuidance['next_activity_id'] ?? $suggestedId,
                'reason'                => $aiGuidance['next_activity_reason'] ?? null,
                'time_estimate_minutes' => $aiGuidance['time_estimate_minutes'] ?? null,
                'prerequisite_warnings' => $aiGuidance['prerequisite_warnings'] ?? [],
            ],
            'lesson_page_navigation' => [
                'allow_page_skip' => $allowNonLinear,
                'show_progress_dots' => true,
            ],
            'activity_overlays' => $activityOverlays,
            'section_overlays' => $sectionOverlays,
        ];
    }

    /**
     * Use Gemini to generate a personalised learning pathway guidance for the student.
     * Falls back to an empty array on any failure so callers use the static fallback.
     *
     * @param  list<array{id: string, title: string, section_title: string}>  $activities
     * @param  array<string, bool>  $completedIds
     * @return array{pathway_message: string|null, next_activity_id: string|null, next_activity_reason: string|null, time_estimate_minutes: int|null, prerequisite_warnings: string[]}
     */
    public function generateAiGuidance(
        string $studentId,
        string $courseId,
        array $contentProfile,
        array $activities,
        array $completedIds,
        array $weakTopics,
    ): array {
        $empty = [
            'pathway_message'       => null,
            'next_activity_id'      => null,
            'next_activity_reason'  => null,
            'time_estimate_minutes' => null,
            'prerequisite_warnings' => [],
        ];

        $apiKey = (string) (config('services.gemini.api_key') ?? '');
        if ($apiKey === '') {
            return $empty;
        }

        $profileHash = md5(json_encode([
            $contentProfile['knowledge_level'] ?? '',
            $contentProfile['pace'] ?? '',
            $contentProfile['quiz_average'] ?? 0,
            $contentProfile['at_risk'] ?? false,
            array_keys($completedIds),
        ]));
        $cacheKey = "nav-guidance:{$studentId}:{$courseId}:{$profileHash}";

        try {
            $cached = Cache::store('file')->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Throwable) {}

        // Build a compact activity list (max 30 to keep prompt short)
        $activityList = '';
        $count = 0;
        foreach ($activities as $act) {
            if ($count >= 30) {
                break;
            }
            $done = isset($completedIds[$act['id']]) ? 'completed' : 'not completed';
            $activityList .= "- [{$done}] {$act['title']} (section: {$act['section_title']})\n";
            $count++;
        }

        $weakTopicsStr = implode(', ', $weakTopics) ?: 'none';
        $knowledgeLevel = $contentProfile['knowledge_level'] ?? 'intermediate';
        $pace           = $contentProfile['pace'] ?? 'medium';
        $quizAvg        = $contentProfile['quiz_average'] ?? 0;
        $atRisk         = ($contentProfile['at_risk'] ?? false) ? 'yes' : 'no';

        $timePerActivity = match ($pace) {
            'slow'  => 25,
            'fast'  => 10,
            default => 15,
        };

        $prompt = <<<TXT
You are a personalised learning pathway advisor inside a university LMS.

Return JSON only — no preamble, no markdown fences:
{
  "pathway_message": "<1-2 sentence personalised message for the student>",
  "next_activity_id": "<activity id from the list, or null>",
  "next_activity_reason": "<1 sentence reason specific to this learner>",
  "time_estimate_minutes": <integer — realistic time for the next activity>,
  "prerequisite_warnings": ["<plain-English warning if a gap exists>"]
}

Rules:
- pathway_message must feel personal and specific — mention the student's situation (risk, quiz score, weak topics).
- next_activity_id must be an exact id from the ACTIVITY LIST below, or null.
- next_activity_reason must explain WHY this specific activity is right for this learner.
- time_estimate_minutes should be realistic given pace "{$pace}" (~{$timePerActivity} min base per activity).
- prerequisite_warnings: flag if the recommended activity requires knowledge from incomplete earlier sections.

LEARNER PROFILE:
- knowledge_level: {$knowledgeLevel}
- pace: {$pace}
- quiz_average: {$quizAvg}%
- weak_topics: {$weakTopicsStr}
- at_risk: {$atRisk}

ACTIVITY LIST (id omitted — use activity title to match, then return id from below):
{$activityList}
TXT;

        // Rebuild the prompt with real IDs (Gemini needs to return an actual ID)
        $activityListWithIds = '';
        $count = 0;
        foreach ($activities as $act) {
            if ($count >= 30) {
                break;
            }
            $done = isset($completedIds[$act['id']]) ? 'completed' : 'not completed';
            $activityListWithIds .= "- id={$act['id']} [{$done}] {$act['title']} (section: {$act['section_title']})\n";
            $count++;
        }

        $prompt = str_replace($activityList, $activityListWithIds, $prompt);

        try {
            $model   = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
            $baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
            $url     = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(25)->withHeaders(['Content-Type' => 'application/json'])->post($url, [
                'contents'          => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig'  => ['temperature' => 0.15, 'maxOutputTokens' => 400],
                'systemInstruction' => ['parts' => [['text' => 'You are a learning pathway advisor. Return valid JSON only.']]],
            ]);

            if (! $response->successful()) {
                Log::warning('NavigationAdaptationService: Gemini guidance failed', ['status' => $response->status()]);
                return $empty;
            }

            $raw     = (string) $response->json('candidates.0.content.parts.0.text', '');
            $raw     = preg_replace('/```json|```/', '', $raw) ?? '';
            $decoded = json_decode(trim($raw), true);

            if (! is_array($decoded)) {
                Log::warning('NavigationAdaptationService: guidance response not valid JSON', ['raw' => substr($raw, 0, 200)]);
                return $empty;
            }

            $result = [
                'pathway_message'       => isset($decoded['pathway_message']) ? (string) $decoded['pathway_message'] : null,
                'next_activity_id'      => isset($decoded['next_activity_id']) ? (string) $decoded['next_activity_id'] : null,
                'next_activity_reason'  => isset($decoded['next_activity_reason']) ? (string) $decoded['next_activity_reason'] : null,
                'time_estimate_minutes' => isset($decoded['time_estimate_minutes']) ? (int) $decoded['time_estimate_minutes'] : null,
                'prerequisite_warnings' => array_values(array_filter(
                    array_map('strval', (array) ($decoded['prerequisite_warnings'] ?? [])),
                    fn ($w) => trim($w) !== '',
                )),
            ];

            try {
                Cache::store('file')->put($cacheKey, $result, now()->addMinutes(15));
            } catch (\Throwable) {}

            return $result;
        } catch (\Throwable $e) {
            Log::warning('NavigationAdaptationService: guidance exception', ['error' => $e->getMessage()]);
            return $empty;
        }
    }

    /** @return list<array{id: string, title: string, section_title: string, sort_order: int}> */
    private function orderedActivities(string $courseId): array
    {
        return DB::table('activities')
            ->join('sections', 'activities.section_id', '=', 'sections.id')
            ->where('sections.course_id', $courseId)
            ->orderBy('sections.sort_order')
            ->orderBy('activities.sort_order')
            ->get([
                'activities.id',
                'activities.name',
                'sections.title as section_title',
                'activities.sort_order',
            ])
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->name,
                'section_title' => $row->section_title,
                'sort_order' => (int) $row->sort_order,
            ])
            ->values()
            ->all();
    }

    private function sectionMatchesWeakTopic(string $sectionTitle, array $weakTopics): bool
    {
        foreach ($weakTopics as $topic) {
            if (stripos($sectionTitle, (string) $topic) !== false || stripos((string) $topic, $sectionTitle) !== false) {
                return true;
            }
        }

        return false;
    }
}
