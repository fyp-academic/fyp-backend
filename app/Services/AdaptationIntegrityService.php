<?php

namespace App\Services;

/**
 * Validates AI adaptations for honesty: instructor source stays immutable in storage;
 * only vetted delivery copies are shown to students.
 */
class AdaptationIntegrityService
{
    // Only near-verbatim output (>= this %) is treated as "no meaningful change" and
    // falls through to presentation_only. Genuine rephrasing/enrichment sits below this
    // and is accepted as adapted, so per-profile delivery differences are not discarded.
    private const MIN_CHANGE_SIMILARITY = 98.0;

    private const MIN_LENGTH_RATIO = 0.45;

    private const MAX_LENGTH_RATIO = 2.2;

    /** @var list<string> */
    private array $aiPreamblePatterns = [
        '/^here is the adapted version:?\s*/i',
        '/^here\'s the adapted version:?\s*/i',
        '/^adapted (version|content):?\s*/i',
        '/^certainly!?\s*/i',
        '/^sure!?\s*/i',
    ];

    /**
     * @return array{
     *   adapted_text: string,
     *   delivery_status: string,
     *   content_adapted: bool,
     *   similarity_percent: float,
     *   integrity: array<string, mixed>,
     *   rejection_reason: string|null,
     * }
     */
    public function assess(string $original, string $candidate, array $settings = []): array
    {
        $original = trim($original);
        $cleaned = $this->stripAiPreamble(trim($candidate));

        if ($cleaned === '') {
            return $this->reject($original, 'empty_ai_output');
        }

        $similarity = 0.0;
        similar_text(
            $this->normalize($original),
            $this->normalize($cleaned),
            $similarity
        );

        // The advanced high-performer path adds extra scenarios + Socratic prompts, so the
        // caller may raise the ceiling for that adaptation only; everyone else keeps 2.2x.
        $maxLengthRatio = (float) ($settings['max_length_ratio'] ?? self::MAX_LENGTH_RATIO);
        $lengthRatio = strlen($cleaned) / max(strlen($original), 1);
        $lengthOk = $lengthRatio >= self::MIN_LENGTH_RATIO && $lengthRatio <= $maxLengthRatio;

        $preventRewrite = (bool) ($settings['prevent_assessment_rewrite'] ?? true);
        if ($preventRewrite && $this->looksLikeAssessment($original)) {
            return $this->reject($original, 'assessment_content_protected');
        }

        $contentChanged = $similarity < self::MIN_CHANGE_SIMILARITY;

        if (! $contentChanged) {
            return [
                'adapted_text' => $original,
                'delivery_status' => 'original_only',
                'content_adapted' => false,
                'similarity_percent' => round($similarity, 1),
                'integrity' => $this->integrityMeta($original, false, $similarity),
                'rejection_reason' => 'no_meaningful_change',
            ];
        }

        if (! $lengthOk) {
            return $this->reject($original, 'length_out_of_bounds');
        }

        return [
            'adapted_text' => $cleaned,
            'delivery_status' => 'adapted',
            'content_adapted' => true,
            'similarity_percent' => round($similarity, 1),
            'integrity' => $this->integrityMeta($original, true, $similarity),
            'rejection_reason' => null,
        ];
    }

    /**
     * @return array{adapted_text: string, delivery_status: string, content_adapted: bool, similarity_percent: float, integrity: array<string, mixed>, rejection_reason: string|null}
     */
    private function reject(string $original, string $reason): array
    {
        return [
            'adapted_text' => $original,
            'delivery_status' => 'original_only',
            'content_adapted' => false,
            'similarity_percent' => 100.0,
            'integrity' => $this->integrityMeta($original, false, 100.0),
            'rejection_reason' => $reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function integrityMeta(string $original, bool $changed, float $similarity): array
    {
        return [
            'instructor_content_immutable' => true,
            'instructor_source_checksum' => md5($original),
            'delivery_changed' => $changed,
            'similarity_to_original_percent' => round($similarity, 1),
            'stored_in_database' => 'content_chunks.chunk_text',
            'student_view' => $changed ? 'adaptation_log.adapted_text' : 'content_chunks.chunk_text',
        ];
    }

    private function stripAiPreamble(string $text): string
    {
        foreach ($this->aiPreamblePatterns as $pattern) {
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        return trim($text);
    }

    private function normalize(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return mb_strtolower(trim($text));
    }

    private function looksLikeAssessment(string $text): bool
    {
        $lower = mb_strtolower($text);

        return (str_contains($lower, 'select the correct answer')
                || str_contains($lower, 'choose the best answer')
                || str_contains($lower, 'true or false:'))
            && substr_count($text, '?') >= 1;
    }

    /**
     * Human-readable transparency message for API/UI.
     */
    public function transparencyMessage(string $deliveryStatus, bool $presentationActive): string
    {
        return match ($deliveryStatus) {
            'adapted' => 'Delivery was personalized for your profile. The instructor\'s original lesson is unchanged and available below.',
            'presentation_only' => 'Instructor content is shown verbatim. Layout and typography are personalized for you.',
            'flagged' => 'Showing instructor original. A previous AI adaptation was reviewed by your instructor.',
            'fallback' => 'Showing instructor original. AI adaptation is temporarily unavailable.',
            default => $presentationActive
                ? 'Showing instructor original content with personalized reading layout.'
                : 'Showing instructor original content.',
        };
    }
}
