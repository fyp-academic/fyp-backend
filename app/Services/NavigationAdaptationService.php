<?php

namespace App\Services;

use App\Models\BehavioralSignal;
use App\Models\LearnerProfile;
use App\Models\UserActivityCompletion;
use Illuminate\Support\Facades\DB;

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
        $guidanceMessage = null;
        if ($suggestedId) {
            $suggested = collect($activities)->firstWhere('id', $suggestedId);
            if ($suggested && ($contentProfile['at_risk'] ?? false)) {
                $guidanceMessage = 'Your learning profile suggests continuing with "'.$suggested['title'].'" to stay on track.';
            } elseif ($suggested && ! empty($weakTopics)) {
                $guidanceMessage = 'Focus on "'.$suggested['section_title'].'" — this area needs strengthening based on your quiz results.';
            } elseif ($suggested) {
                $guidanceMessage = 'Continue with "'.$suggested['title'].'" to maintain your learning pathway.';
            }
        }

        return [
            'mode' => $openNavigation ? 'open' : ($structuredPathway ? 'structured' : 'balanced'),
            'allow_non_linear_jump' => $allowNonLinear,
            'enforce_sequence' => $enforceSequence,
            'navigation_pattern' => $navigationPattern,
            'direct_guidance' => [
                'enabled' => $guidanceMessage !== null,
                'message' => $guidanceMessage,
                'suggested_activity_id' => $suggestedId,
            ],
            'lesson_page_navigation' => [
                'allow_page_skip' => $allowNonLinear,
                'show_progress_dots' => true,
            ],
            'activity_overlays' => $activityOverlays,
            'section_overlays' => $sectionOverlays,
        ];
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
