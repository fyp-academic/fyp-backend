<?php

namespace App\Services;

use App\Models\RiskScore;
use App\Models\User;

/**
 * Presentation-level adaptation: typography, density, and reading layout (UI layer).
 * Does not modify instructor content in storage.
 */
class PresentationAdaptationService
{
    /**
     * @param  array<string, mixed>  $contentProfile
     * @return array<string, mixed>
     */
    public function resolve(array $contentProfile, ?User $user, ?RiskScore $risk): array
    {
        $pace = $contentProfile['pace'] ?? 'medium';
        $modality = $contentProfile['preferred_modality'] ?? 'text';
        $knowledgeLevel = $contentProfile['knowledge_level'] ?? 'intermediate';
        $atRisk = $contentProfile['at_risk'] ?? false;
        $primaryProfile = $contentProfile['primary_profile'] ?? null;
        $vark = $contentProfile['vark_style'] ?? $user?->vark_style;

        $textDensity = match ($pace) {
            'slow' => 'spacious',
            'fast' => 'compact',
            default => 'comfortable',
        };

        $fontScale = match (true) {
            $pace === 'slow' || $knowledgeLevel === 'novice' => 1.12,
            $pace === 'fast' && $knowledgeLevel === 'advanced' => 0.94,
            default => 1.0,
        };

        $layoutMode = match ($modality) {
            'visual' => 'visual',
            'example-based' => 'example',
            default => match ($primaryProfile) {
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
            'compact' => 1.5,
            default => 1.65,
        };

        $contentMaxWidth = match ($layoutMode) {
            'visual' => '56rem',
            'focus' => '40rem',
            'exploratory' => '52rem',
            default => '48rem',
        };

        $cardVariant = match ($layoutMode) {
            'visual' => 'elevated-visual',
            'example' => 'example-first',
            'focus' => 'narrow-focus',
            'exploratory' => 'wide-explore',
            default => 'standard',
        };

        $isActive = $fontScale !== 1.0
            || $textDensity !== 'comfortable'
            || $layoutMode !== 'standard'
            || $colorScheme !== 'default'
            || ($pace === 'slow' || $knowledgeLevel === 'novice');

        return [
            'is_active' => $isActive,
            'text_density' => $textDensity,
            'font_scale' => $fontScale,
            'layout_mode' => $layoutMode,
            'line_height' => $lineHeight,
            'content_max_width' => $contentMaxWidth,
            'show_step_numbers' => $pace === 'slow' || $knowledgeLevel === 'novice',
            'visual_emphasis' => $modality === 'visual' || $vark === 'visual',
            'highlight_weak_topics' => ! empty($contentProfile['weak_topics']),
            'color_scheme' => $colorScheme,
            'card_variant' => $cardVariant,
            'reading_rail' => $layoutMode === 'focus' ? 'centered' : 'default',
            'typography_class' => "personalization-{$textDensity}-{$layoutMode}",
        ];
    }
}
