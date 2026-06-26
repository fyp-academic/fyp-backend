<?php

namespace App\Services;

use App\Models\LearnerProfile;
use App\Models\RiskScore;
use App\Models\User;

/**
 * Presentation-level adaptation: typography, density, reading layout, and player mode (UI layer).
 * Does not modify instructor content in storage.
 */
class PresentationAdaptationService
{
    private const VALID_MODES = ['guided_steps', 'visual_discovery', 'deep_focus', 'narrative_example', 'standard'];

    /**
     * Map an explicit student style choice (declared modality and/or VARK) to a presentation
     * mode. Presentation-only: this picks WHICH player/layout the learner sees, never content
     * adaptation depth. Mirrors the modality mapping in StudentProfileService and
     * deriveModeFallback. Returns null when the style yields no specific preference.
     */
    public static function modeForStyle(?string $modality, ?string $vark = null): ?string
    {
        if ($modality === 'visual' || $vark === 'visual') {
            return 'visual_discovery';
        }
        if ($modality === 'example-based' || $vark === 'kinesthetic') {
            return 'narrative_example';
        }
        if ($modality === 'text' || in_array($vark, ['reading', 'read_write', 'auditory'], true)) {
            return 'deep_focus';
        }

        return null;
    }

    /**
     * Ask Gemini to select the optimal presentation mode for this learner.
     * Falls back to rule-based derivation on any failure.
     *
     * @param  array<string, mixed>  $contentProfile
     */
    public function selectMode(array $contentProfile, string $contentType = 'lecture'): string
    {
        // Explicit student style choice wins — a learner controls how content is PRESENTED.
        // Sits above the instructor pin so "change my learning style" always takes effect
        // (content adaptation depth and navigation are unaffected and decided elsewhere).
        $studentMode = $contentProfile['preferred_presentation_mode'] ?? null;
        if (is_string($studentMode) && in_array($studentMode, self::VALID_MODES, true)) {
            return $studentMode;
        }

        // Instructor override is the default when the student has made no explicit choice.
        $studentId = (string) ($contentProfile['student_id'] ?? '');
        $courseId  = (string) ($contentProfile['course_id'] ?? '');
        if ($studentId !== '' && $courseId !== '') {
            $override = LearnerProfile::where('learner_id', $studentId)
                ->where('course_id', $courseId)
                ->value('adaptation_mode_override');
            if (is_string($override) && in_array($override, self::VALID_MODES, true)) {
                return $override;
            }
        }

        // Warm-up gate: until the learner has real evidence on this course, serve
        // neutral delivery. Keeps the AI honest — no profile is fabricated day one.
        if (($contentProfile['personalization_ready'] ?? true) === false) {
            return 'standard';
        }

        // Struggling / novice learners are always given the signaling (guided_steps)
        // layout, guaranteeing Mayer's Signaling principle regardless of the AI pick.
        if ($this->isStruggling($contentProfile)) {
            return 'guided_steps';
        }

        // Rule-based selection (no Gemini call). The deterministic rules already map every
        // profile to the correct mode, and avoiding an LLM call here keeps the shared Gemini
        // quota for content adaptation + the AI tutor instead of spending it on mode picking.
        return $this->deriveModeFallback($contentProfile);
    }

    /**
     * @param  array<string, mixed>  $contentProfile
     * @return array<string, mixed>
     */
    public function resolve(array $contentProfile, ?User $user, ?RiskScore $risk, string $mode = ''): array
    {
        // Warm-up gate: neutral, inactive presentation until personalization is ready.
        if (($contentProfile['personalization_ready'] ?? true) === false) {
            return [
                'is_active'             => false,
                'text_density'          => 'comfortable',
                'font_scale'            => 1.0,
                'layout_mode'           => 'standard',
                'line_height'           => 1.65,
                'content_max_width'     => '48rem',
                'show_step_numbers'     => false,
                'visual_emphasis'       => false,
                'highlight_weak_topics' => false,
                'color_scheme'          => 'default',
                'card_variant'          => 'standard',
                'reading_rail'          => 'default',
                'typography_class'      => 'personalization-comfortable-standard',
                'mode'                  => 'standard',
                'mode_config'           => $this->modeConfig('standard'),
            ];
        }

        $pace           = $contentProfile['pace'] ?? 'medium';
        $modality       = $contentProfile['preferred_modality'] ?? 'text';
        $knowledgeLevel = $contentProfile['knowledge_level'] ?? 'intermediate';
        $atRisk         = $contentProfile['at_risk'] ?? false;
        $primaryProfile = $contentProfile['primary_profile'] ?? null;
        $vark           = $contentProfile['vark_style'] ?? $user?->vark_style;

        if ($mode === '') {
            $mode = $this->deriveModeFallback($contentProfile, $user);
        }

        $textDensity = match ($pace) {
            'slow'  => 'spacious',
            'fast'  => 'compact',
            default => 'comfortable',
        };

        $fontScale = match (true) {
            $pace === 'slow' || $knowledgeLevel === 'novice'             => 1.12,
            $pace === 'fast' && $knowledgeLevel === 'advanced'           => 0.94,
            default                                                       => 1.0,
        };

        $layoutMode = match ($modality) {
            'visual'         => 'visual',
            'example-based'  => 'example',
            default          => match ($primaryProfile) {
                'T' => 'focus',
                'H' => 'exploratory',
                default => 'standard',
            },
        };

        if ($vark === 'visual') {
            $layoutMode = 'visual';
        }

        $colorScheme = $atRisk ? 'calm' : 'default';
        if (($risk?->tier ?? '') === 'RED') {
            $colorScheme = 'high_contrast';
        }

        $lineHeight = match ($textDensity) {
            'spacious' => 1.85,
            'compact'  => 1.5,
            default    => 1.65,
        };

        $contentMaxWidth = match ($layoutMode) {
            'visual'      => '56rem',
            'focus'       => '40rem',
            'exploratory' => '52rem',
            default       => '48rem',
        };

        $cardVariant = match ($layoutMode) {
            'visual'      => 'elevated-visual',
            'example'     => 'example-first',
            'focus'       => 'narrow-focus',
            'exploratory' => 'wide-explore',
            default       => 'standard',
        };

        $isActive = $fontScale !== 1.0
            || $textDensity !== 'comfortable'
            || $layoutMode !== 'standard'
            || $colorScheme !== 'default'
            || ($pace === 'slow' || $knowledgeLevel === 'novice');

        return [
            'is_active'          => $isActive,
            'text_density'       => $textDensity,
            'font_scale'         => $fontScale,
            'layout_mode'        => $layoutMode,
            'line_height'        => $lineHeight,
            'content_max_width'  => $contentMaxWidth,
            'show_step_numbers'  => $pace === 'slow' || $knowledgeLevel === 'novice',
            'visual_emphasis'    => $modality === 'visual' || $vark === 'visual',
            'highlight_weak_topics' => ! empty($contentProfile['weak_topics']),
            'color_scheme'       => $colorScheme,
            'card_variant'       => $cardVariant,
            'reading_rail'       => $layoutMode === 'focus' ? 'centered' : 'default',
            'typography_class'   => "personalization-{$textDensity}-{$layoutMode}",
            'mode'               => $mode,
            'mode_config'        => $this->modeConfig($mode),
        ];
    }

    /**
     * Returns frontend rendering hints for a given presentation mode.
     *
     * @return array<string, mixed>
     */
    private function modeConfig(string $mode): array
    {
        return match ($mode) {
            'guided_steps' => [
                'numbered_steps'  => true,
                'bold_key_terms'  => true,
                'use_highlights'  => true,
                'structure'       => 'steps',
                'density'         => 'spacious',
            ],
            'visual_discovery' => [
                'prefer_tables'   => true,
                'prefer_headings' => true,
                'numbered_steps'  => false,
                'structure'       => 'visual',
                'density'         => 'comfortable',
            ],
            'deep_focus' => [
                'dense_prose'     => true,
                'show_connections'=> true,
                'numbered_steps'  => false,
                'structure'       => 'prose',
                'density'         => 'compact',
            ],
            'narrative_example' => [
                'example_first'   => true,
                'numbered_steps'  => false,
                'structure'       => 'narrative',
                'density'         => 'comfortable',
            ],
            default => [
                'numbered_steps'  => false,
                'structure'       => 'standard',
                'density'         => 'comfortable',
            ],
        };
    }

    /**
     * Rule-based fallback mode derivation when AI selection is unavailable.
     *
     * @param  array<string, mixed>  $contentProfile
     */
    private function deriveModeFallback(array $contentProfile, ?User $user = null): string
    {
        $pace           = $contentProfile['pace'] ?? 'medium';
        $knowledgeLevel = $contentProfile['knowledge_level'] ?? 'intermediate';
        $vark           = $contentProfile['vark_style'] ?? $user?->vark_style ?? '';
        $modality       = $contentProfile['preferred_modality'] ?? 'text';
        $hatc           = $contentProfile['primary_profile'] ?? '';

        if ($pace === 'slow' || $knowledgeLevel === 'novice' || $this->isStruggling($contentProfile)) {
            return 'guided_steps';
        }
        if ($vark === 'visual' || $modality === 'visual') {
            return 'visual_discovery';
        }
        if ($knowledgeLevel === 'advanced' && $pace === 'fast') {
            return 'deep_focus';
        }
        if ($modality === 'example-based' || $hatc === 'C') {
            return 'narrative_example';
        }

        return 'standard';
    }

    /**
     * A learner is "struggling" when they are flagged at-risk, classed as a novice,
     * or averaging below 50% — these learners always receive the signaling layout.
     *
     * @param  array<string, mixed>  $contentProfile
     */
    private function isStruggling(array $contentProfile): bool
    {
        return ($contentProfile['at_risk'] ?? false) === true
            || ($contentProfile['knowledge_level'] ?? '') === 'novice'
            || (float) ($contentProfile['quiz_average'] ?? 0) < 50;
    }
}
