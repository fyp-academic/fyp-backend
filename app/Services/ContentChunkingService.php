<?php

namespace App\Services;

use App\Models\ContentChunk;
use Illuminate\Support\Str;

class ContentChunkingService
{
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
