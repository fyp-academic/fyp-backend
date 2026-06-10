<?php

namespace App\Services;

use App\Models\ContentChunk;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContentChunkingService
{
    private const VALID_ROLES = ['introduction', 'concept', 'example', 'activity', 'summary'];

    /**
     * Chunk content and enrich each chunk with AI-classified semantic role, key terms,
     * and lesson position. Falls back to the plain chunk() path if AI is unavailable.
     *
     * @return array Array of chunk IDs
     */
    public function chunkWithSemantics(
        string $contentId,
        string $contentText,
        string $contentType = 'lecture',
        string $contentSource = 'lesson_page',
        bool $classifyWithAi = true,
    ): array {
        $chunkIds = $this->chunk($contentId, $contentText, $contentType, $contentSource);

        if (! $classifyWithAi) {
            return $chunkIds;
        }

        $apiKey = (string) (config('services.gemini.api_key') ?? '');
        if ($apiKey === '') {
            return $chunkIds;
        }

        $chunks = ContentChunk::where('content_id', $contentId)
            ->where('content_source', $contentSource)
            ->orderBy('chunk_index')
            ->get(['id', 'chunk_index', 'chunk_text']);

        if ($chunks->isEmpty()) {
            return $chunkIds;
        }

        $classifications = $this->classifyChunks($chunks->toArray(), $apiKey);
        $total = $chunks->count();

        foreach ($chunks as $chunk) {
            $cl = $classifications[$chunk->chunk_index] ?? [];
            $role = in_array($cl['semantic_role'] ?? '', self::VALID_ROLES, true)
                ? $cl['semantic_role']
                : 'concept';
            $keyTerms = array_values(array_filter(
                array_map('strval', (array) ($cl['key_terms'] ?? [])),
                fn ($t) => trim($t) !== '',
            ));
            $positionPct = (int) round(($chunk->chunk_index / max(1, $total - 1)) * 100);

            $chunk->update([
                'semantic_role'        => $role,
                'key_terms'            => $keyTerms,
                'lesson_position_pct'  => $positionPct,
            ]);
        }

        return $chunkIds;
    }

    /**
     * Send a single Gemini batch call to classify all chunks at once.
     *
     * @param  array<int, array{chunk_index: int, chunk_text: string}>  $chunks
     * @return array<int, array{semantic_role: string, key_terms: string[]}>  keyed by chunk_index
     */
    private function classifyChunks(array $chunks, string $apiKey): array
    {
        $model = (string) (config('services.gemini.model') ?? 'gemini-2.5-flash');
        $baseUrl = rtrim((string) (config('services.gemini.base_url') ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');

        $chunkList = '';
        foreach ($chunks as $c) {
            $idx = (int) $c['chunk_index'];
            $preview = mb_substr(strip_tags((string) $c['chunk_text']), 0, 300);
            $chunkList .= "CHUNK {$idx}:\n{$preview}\n\n";
        }

        $prompt = <<<TXT
Classify each educational content chunk below.

Return a JSON array ONLY — no preamble, no markdown fences.

Each element: {"chunk_index": <int>, "semantic_role": "<role>", "key_terms": ["<term>", ...]}

Allowed roles: introduction, concept, example, activity, summary
- introduction: opening context or objectives
- concept: core theoretical or factual content
- example: worked examples, illustrations, case studies
- activity: exercises, questions, tasks for the learner
- summary: closing recap or key takeaways

key_terms: up to 5 important domain terms introduced or defined in that chunk. Empty array if none.

CHUNKS:
{$chunkList}
TXT;

        try {
            $url = "{$baseUrl}/models/{$model}:generateContent?key={$apiKey}";
            $response = Http::timeout(45)->withHeaders(['Content-Type' => 'application/json'])->post($url, [
                'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => 1500],
                'systemInstruction' => ['parts' => [['text' => 'You classify educational content chunks. Return JSON array only.']]],
            ]);

            if (! $response->successful()) {
                Log::warning('ContentChunkingService: Gemini classification failed', ['status' => $response->status()]);
                return [];
            }

            $raw = $response->json('candidates.0.content.parts.0.text', '');
            $raw = preg_replace('/```json|```/', '', (string) $raw) ?? '';
            $decoded = json_decode(trim($raw), true);

            if (! is_array($decoded)) {
                Log::warning('ContentChunkingService: classification response not valid JSON', ['raw' => substr($raw, 0, 200)]);
                return [];
            }

            $result = [];
            foreach ($decoded as $item) {
                if (isset($item['chunk_index'])) {
                    $result[(int) $item['chunk_index']] = $item;
                }
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('ContentChunkingService: classification exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Split content into semantic chunks and persist them.
     *
     * @return array Array of chunk IDs
     */
    public function chunk(
        string $contentId,
        string $contentText,
        string $contentType = 'lecture',
        string $contentSource = 'lesson_page',
    ): array {
        ContentChunk::where('content_id', $contentId)
            ->where('content_source', $contentSource)
            ->delete();

        $rawChunks = $this->splitIntoChunks($contentText);
        $chunkIds = [];

        foreach ($rawChunks as $index => $chunkText) {
            $chunk = ContentChunk::create([
                'id' => Str::uuid()->toString(),
                'content_id' => $contentId,
                'content_source' => $contentSource,
                'chunk_index' => $index,
                'chunk_text' => $chunkText,
                'chunk_type' => $this->mapContentType($contentType),
            ]);
            $chunkIds[] = $chunk->id;
        }

        return $chunkIds;
    }

    /**
     * Split text into semantic chunks.
     */
    private function splitIntoChunks(string $text): array
    {
        // Convert HTML to plain text with structural markers
        $text = $this->htmlToText($text);

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Split on double newline or headings
        $segments = preg_split('/\n\n+|(?=\n#{1,2}\s)|(?=\n[A-Z][A-Z\s]{2,}\n)/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $segments = array_map('trim', $segments);
        $segments = array_filter($segments);

        $chunks = [];
        $currentChunk = '';
        $currentWords = 0;

        foreach ($segments as $segment) {
            $wordCount = str_word_count($segment);

            // If segment alone exceeds max, split it at sentence boundaries
            if ($wordCount > 400) {
                if ($currentChunk !== '') {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                    $currentWords = 0;
                }

                $sentenceChunks = $this->splitAtSentences($segment, 400);
                foreach ($sentenceChunks as $sc) {
                    $wc = str_word_count($sc);
                    if ($currentWords + $wc > 400) {
                        if ($currentChunk !== '') {
                            $chunks[] = trim($currentChunk);
                        }
                        $currentChunk = $sc;
                        $currentWords = $wc;
                    } else {
                        $currentChunk .= ($currentChunk === '' ? '' : ' ') . $sc;
                        $currentWords += $wc;
                    }
                }
                continue;
            }

            // If adding this segment keeps us under max, accumulate
            if ($currentWords + $wordCount <= 400) {
                $currentChunk .= ($currentChunk === '' ? '' : "\n\n") . $segment;
                $currentWords += $wordCount;
            } else {
                // Flush current chunk
                if ($currentChunk !== '') {
                    $chunks[] = trim($currentChunk);
                }
                $currentChunk = $segment;
                $currentWords = $wordCount;
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = trim($currentChunk);
        }

        // Merge chunks that are too short
        $chunks = $this->mergeShortChunks($chunks);

        return $chunks;
    }

    /**
     * Split a long text at nearest sentence boundaries.
     */
    private function splitAtSentences(string $text, int $maxWords): array
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $result = [];
        $current = '';
        $words = 0;

        foreach ($sentences as $sentence) {
            $sw = str_word_count($sentence);
            if ($words + $sw > $maxWords && $current !== '') {
                $result[] = trim($current);
                $current = $sentence;
                $words = $sw;
            } else {
                $current .= ($current === '' ? '' : ' ') . $sentence;
                $words += $sw;
            }
        }

        if ($current !== '') {
            $result[] = trim($current);
        }

        return $result ?: [$text];
    }

    /**
     * Merge chunks under 50 words with the next chunk.
     */
    private function mergeShortChunks(array $chunks): array
    {
        $merged = [];
        $buffer = '';
        $bufferWords = 0;

        foreach ($chunks as $i => $chunk) {
            $words = str_word_count($chunk);

            if ($bufferWords + $words < 50) {
                $buffer .= ($buffer === '' ? '' : "\n\n") . $chunk;
                $bufferWords += $words;
            } else {
                if ($buffer !== '') {
                    $merged[] = trim($buffer . "\n\n" . $chunk);
                    $buffer = '';
                    $bufferWords = 0;
                } else {
                    $merged[] = $chunk;
                }
            }
        }

        if ($buffer !== '') {
            if (count($merged) > 0) {
                $merged[count($merged) - 1] .= "\n\n" . $buffer;
            } else {
                $merged[] = $buffer;
            }
        }

        return $merged;
    }

    private function mapContentType(string $type): string
    {
        $map = [
            'content' => 'lecture',
            'page' => 'lecture',
            'lesson' => 'lecture',
            'note' => 'note',
            'pdf' => 'pdf_text',
            'pptx' => 'pdf_text',
            'docx' => 'pdf_text',
            'doc' => 'pdf_text',
            'video' => 'lecture',
            'youtube' => 'lecture',
            'h5p' => 'lecture',
            'scorm' => 'lecture',
            'example' => 'example',
            'quiz' => 'quiz',
            'assessment' => 'assessment',
            'question' => 'quiz',
        ];

        return $map[strtolower($type)] ?? 'lecture';
    }

    /**
     * Convert HTML to plain text with structural markers for chunking.
     */
    private function htmlToText(string $html): string
    {
        // Replace common block elements with newlines
        $replacements = [
            '/<\/?p[^>]*>/i' => "\n\n",
            '/<br\s*\/?>/i' => "\n",
            '/<\/?div[^>]*>/i' => "\n\n",
            '/<h[1-6][^>]*>/i' => "\n\n# ",
            '/<\/h[1-6]>/i' => "\n",
            '/<li[^>]*>/i' => "\n- ",
            '/<\/li>/i' => "",
            '/<ul[^>]*>/i' => "\n",
            '/<\/ul>/i' => "\n",
            '/<ol[^>]*>/i' => "\n",
            '/<\/ol>/i' => "\n",
        ];

        $text = $html;
        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        // Strip remaining tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse excessive newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }
}
