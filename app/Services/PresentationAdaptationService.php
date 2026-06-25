<?php

namespace App\Services;

use App\Models\LearnerProfile;
use App\Models\RiskScore;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $apiKey = (string) (config('services.gemini.api_key') ?? '');
        if ($apiKey === '') {
            return $this->deriveModeFallback($contentProfile);
        }

        $studentId = (string) ($contentProfile['student_id'] ?? '');
        $courseId  = (string) ($contentProfile['course_id'] ?? '');
        $profileHash = md5(json_encode([
            $contentProfile['knowledge_level'] ?? '',
            $contentProfile['pace'] ?? '',
            $contentProfile['vark_style'] ?? '',
            $contentProfile['preferred_modality'] ?? '',
            $contentProfile['primary_profile'] ?? '',
            $contentType,
        ]));
        $cacheKey = "pres-mode:{$studentId}:{$courseId}:{$profileHash}";

        try {
            $cached = Cache::store('file')->get($cacheKey);
            if (is_string($cached) && in_array($cached, self::VALID_MODES, true)) {
                return $cached;
            }
        } catch (\Throwable) {}


        $knowledgeLevel = $contentProfile['knowledge_level'] ?? 'intermediate';
        $pace           = $contentProfile['pace'] ?? 'medium';
        $vark           = $contentProfile['vark_style'] ?? 'unknown';
        $modality       = $contentProfile['preferred_modality'] ?? 'text';
        $hatc           = $contentProfile['primary_profile'] ?? 'unknown';
        $atRisk         = ($contentProfile['at_risk'] ?? false) ? 'yes' : 'no';
        $quizAvg        = $contentProfile['quiz_average'] ?? 0;

        $prompt = <<<TXT
You are an instructional design advisor. Select the single best presentation mode for this learner.

Return JSON only: {"mode": "<mode_name>"}

Allowed modes:
- guided_steps     — best for novice or slow-paced learners; numbered steps with signaling
- visual_discovery — best for visual VARK learners or those who prefer visual modality
- deep_focus       — best for advanced, fast-paced learners; dense academic prose
- narrative_example — best for example-based modality learners or Caring (C) HATC profile
- standard         — use when none of the above clearly applies

Learner profile:
- knowledge_level: {$knowledgeLevel}
- pace: {$pace}
- vark_style: {$vark}
- preferred_modality: {$modality}
- HATC primary profile: {$hatc}
- at_risk: {$atRisk}
- quiz_average: {$quizAvg}%
- content_type: {$contentType}
TXT;

        try {
            $model   = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
            $baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
            $url     = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";

            $response = Http::timeout(20)->withHeaders(['Content-Type' => 'application/json'])->post($url, [
                'contents'          => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig'  => ['temperature' => 0.1, 'maxOutputTokens' => 50],
                'systemInstruction' => ['parts' => [['text' => 'Return JSON only.']]],
            ]);

            if ($response->successful()) {
                $raw     = (string) $response->json('candidates.0.content.parts.0.text', '');
                $raw     = preg_replace('/```json|```/', '', $raw) ?? '';
                $decoded = json_decode(trim($raw), true);
                $mode    = (string) ($decoded['mode'] ?? '');

                if (in_array($mode, self::VALID_MODES, true)) {
                    try {
                        Cache::store('file')->put($cacheKey, $mode, now()->addMinutes(30));
                    } catch (\Throwable) {}

                    return $mode;
                }
            }

            Log::warning('PresentationAdaptationService: mode selection failed', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 200),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PresentationAdaptationService: mode selection exception', ['error' => $e->getMessage()]);
        }

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
