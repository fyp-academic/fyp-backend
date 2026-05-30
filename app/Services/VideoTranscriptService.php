<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VideoTranscriptService
{
    private const MAX_INLINE_VIDEO_BYTES = 18_000_000;
    private const MAX_TRANSCRIPT_CHARS = 12000;

    private string $apiKey;
    private string $model;
    private string $baseUrl;
    private string $youtubeApiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key', '');
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $this->baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->youtubeApiKey = (string) config('services.youtube.api_key', '');
    }

    public function transcribeYouTube(string $url, ?string $fallbackTitle = null): string
    {
        $normalizedUrl = $this->normalizeYouTubeUrl($url);
        if (! $normalizedUrl) {
            return '';
        }

        return Cache::remember(
            'video-transcript:youtube:' . md5($normalizedUrl),
            now()->addHours(24),
            function () use ($normalizedUrl, $fallbackTitle): string {
                $metadata = $this->youtubeMetadata($normalizedUrl, $fallbackTitle);
                $fallback = $this->buildYouTubeFallbackText($metadata, $normalizedUrl);

                if ($this->apiKey === '') {
                    return $fallback;
                }

                $prompt = <<<TXT
Generate transcript support for this educational YouTube video.

Use the YouTube reference and metadata below.
If the spoken content is directly accessible, produce:
1. A concise cleaned transcript of the important spoken teaching points.
2. A short list of key concepts.

If the spoken content is NOT directly accessible from the reference, do NOT invent dialogue.
Instead, return a faithful study-ready context summary using ONLY the provided metadata and clearly state that a verbatim transcript could not be verified.

YouTube URL: {$normalizedUrl}
Title: {$metadata['title']}
Description:
{$metadata['description']}
TXT;

                $generated = $this->generateText($prompt);

                return $generated !== ''
                    ? $this->truncateText($generated)
                    : $fallback;
            }
        );
    }

    public function transcribeLocalVideo(string $path, ?string $publicUrl = null, ?string $title = null): string
    {
        $absolutePath = $this->resolveStoragePath($path);
        if (! is_file($absolutePath)) {
            return '';
        }

        $size = (int) (filesize($absolutePath) ?: 0);
        $cacheKey = 'video-transcript:local:' . md5($absolutePath . '|' . $size . '|' . filemtime($absolutePath));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($absolutePath, $size, $publicUrl, $title): string {
            $filename = basename($absolutePath);

            if ($this->apiKey === '') {
                return "Video file: {$filename}";
            }

            if ($size <= 0 || $size > self::MAX_INLINE_VIDEO_BYTES) {
                Log::info('Skipping Gemini local video transcript: unsupported file size', [
                    'path' => $absolutePath,
                    'size' => $size,
                ]);

                return "Video file: {$filename}";
            }

            $bytes = @file_get_contents($absolutePath);
            if ($bytes === false) {
                return "Video file: {$filename}";
            }

            $mimeType = $this->detectMimeType($absolutePath);
            $prompt = <<<TXT
Transcribe the educational content from this uploaded lesson video.

Rules:
- Be faithful to what is said or shown.
- Prefer clear teaching points over filler speech.
- If a short portion is unclear, mark it as [inaudible].
- End with 3-6 bullet points of key concepts.
- Do not invent facts that are not present in the video.

Video title: {$title}
Source URL: {$publicUrl}
TXT;

            $generated = $this->generateMultimodalText([
                [
                    'inline_data' => [
                        'mime_type' => $mimeType,
                        'data' => base64_encode($bytes),
                    ],
                ],
                ['text' => $prompt],
            ]);

            return $generated !== ''
                ? $this->truncateText($generated)
                : "Video file: {$filename}";
        });
    }

    public function enrichLessonPageHtml(string $html): string
    {
        if (trim($html) === '' || ! $this->looksLikeVideoMarkup($html)) {
            return $html;
        }

        $videos = $this->extractEmbeddedVideos($html);
        if ($videos === []) {
            return $html;
        }

        $sections = [];

        foreach ($videos as $index => $video) {
            $transcript = match ($video['kind']) {
                'youtube' => $this->transcribeYouTube($video['url']),
                'local' => $this->transcribeLocalVideo($video['path'], $video['url']),
                default => '',
            };

            if (trim($transcript) === '' || str_word_count($transcript) < 20) {
                continue;
            }

            $label = $video['kind'] === 'youtube'
                ? 'Embedded YouTube Video'
                : 'Embedded Lesson Video';

            $sections[] = sprintf(
                '<section><h2>%s %d Transcript Support</h2><p>%s</p></section>',
                $label,
                $index + 1,
                nl2br(e($transcript))
            );
        }

        if ($sections === []) {
            return $html;
        }

        return $html . "\n" .
            '<section data-ai-video-transcript="true">' .
            '<h1>AI Video Transcript Support</h1>' .
            implode("\n", $sections) .
            '</section>';
    }

    /**
     * @return array<int, array{kind: string, url: string, path: string|null}>
     */
    private function extractEmbeddedVideos(string $html): array
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $previousState = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        if (! $loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $videos = [];
        $seen = [];

        foreach ($xpath->query('//video') ?: [] as $videoNode) {
            $src = '';
            if ($videoNode instanceof \DOMElement) {
                $src = trim((string) $videoNode->getAttribute('src'));

                if ($src === '') {
                    foreach ($videoNode->getElementsByTagName('source') as $sourceNode) {
                        if ($sourceNode instanceof \DOMElement) {
                            $src = trim((string) $sourceNode->getAttribute('src'));
                            if ($src !== '') {
                                break;
                            }
                        }
                    }
                }
            }

            if ($src === '') {
                continue;
            }

            $path = $this->resolvePublicStoragePath($src);
            if (! $path) {
                continue;
            }

            $key = 'local:' . $path;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $videos[] = [
                'kind' => 'local',
                'url' => $src,
                'path' => $path,
            ];
        }

        foreach ($xpath->query('//iframe') ?: [] as $iframeNode) {
            if (! $iframeNode instanceof \DOMElement) {
                continue;
            }

            $src = trim((string) $iframeNode->getAttribute('src'));
            $normalized = $this->normalizeYouTubeUrl($src);
            if (! $normalized) {
                continue;
            }

            $key = 'youtube:' . $normalized;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $videos[] = [
                'kind' => 'youtube',
                'url' => $normalized,
                'path' => null,
            ];
        }

        return $videos;
    }

    private function looksLikeVideoMarkup(string $html): bool
    {
        return str_contains($html, '<video')
            || str_contains($html, '<iframe')
            || str_contains($html, 'youtube.com')
            || str_contains($html, 'youtu.be')
            || str_contains($html, 'youtube-nocookie.com');
    }

    private function buildYouTubeFallbackText(array $metadata, string $url): string
    {
        $title = $metadata['title'] ?: 'Untitled YouTube video';
        $description = trim((string) ($metadata['description'] ?? ''));

        $parts = [
            "Video title: {$title}",
            "Video URL: {$url}",
        ];

        if ($description !== '') {
            $parts[] = 'Description: ' . $description;
        }

        return $this->truncateText(implode("\n", $parts));
    }

    /**
     * @return array{title: string, description: string}
     */
    private function youtubeMetadata(string $url, ?string $fallbackTitle): array
    {
        $videoId = $this->extractYouTubeId($url);
        $metadata = [
            'title' => $fallbackTitle ?: 'Untitled YouTube video',
            'description' => '',
        ];

        if (! $videoId || $this->youtubeApiKey === '') {
            return $metadata;
        }

        try {
            $response = Http::timeout(10)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet',
                'id' => $videoId,
                'key' => $this->youtubeApiKey,
            ]);

            if ($response->failed()) {
                Log::warning('YouTube metadata request failed', [
                    'status' => $response->status(),
                    'video_id' => $videoId,
                ]);

                return $metadata;
            }

            $snippet = $response->json('items.0.snippet', []);

            return [
                'title' => (string) ($snippet['title'] ?? $metadata['title']),
                'description' => (string) ($snippet['description'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::warning('YouTube metadata extraction failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $metadata;
        }
    }

    private function generateText(string $prompt): string
    {
        return $this->generateMultimodalText([
            ['text' => $prompt],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $parts
     */
    private function generateMultimodalText(array $parts): string
    {
        if ($this->apiKey === '') {
            return '';
        }

        $payload = [
            'contents' => [[
                'role' => 'user',
                'parts' => $parts,
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 2200,
                'topP' => 0.8,
            ],
            'systemInstruction' => [
                'parts' => [[
                    'text' => 'You extract study-ready transcript support from educational videos. Be precise, avoid hallucinations, and never invent unseen content.',
                ]],
            ],
        ];

        try {
            $response = Http::timeout(90)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}", $payload);

            if ($response->failed()) {
                Log::warning('Gemini video transcript request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return '';
            }

            $parts = $response->json('candidates.0.content.parts', []);
            $text = collect($parts)
                ->pluck('text')
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->implode("\n");

            return trim($text);
        } catch (\Throwable $e) {
            Log::warning('Gemini video transcript request errored', [
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function normalizeYouTubeUrl(string $url): ?string
    {
        $videoId = $this->extractYouTubeId($url);

        return $videoId ? "https://www.youtube.com/watch?v={$videoId}" : null;
    }

    private function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_-]{11})/i',
            '/youtube\.com\/embed\/([A-Za-z0-9_-]{11})/i',
            '/youtube\-nocookie\.com\/embed\/([A-Za-z0-9_-]{11})/i',
            '/youtube\.com\/shorts\/([A-Za-z0-9_-]{11})/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function truncateText(string $text): string
    {
        $normalized = preg_replace("/\r\n|\r/", "\n", trim($text)) ?? '';
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;

        return mb_substr($normalized, 0, self::MAX_TRANSCRIPT_CHARS);
    }

    private function detectMimeType(string $path): string
    {
        $detected = @mime_content_type($path);
        if (is_string($detected) && $detected !== '') {
            return $detected;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            default => 'video/mp4',
        };
    }

    private function resolvePublicStoragePath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $marker = '/storage/';
        $position = strpos($path, $marker);
        if ($position === false) {
            return null;
        }

        $relative = ltrim(substr($path, $position + strlen($marker)), '/');

        return $relative !== ''
            ? storage_path('app/public/' . $relative)
            : null;
    }

    private function resolveStoragePath(string $path): string
    {
        if (str_starts_with($path, 'public/')) {
            return storage_path('app/' . $path);
        }

        if (str_starts_with($path, 'files/') || str_starts_with($path, 'videos/') || str_starts_with($path, 'lesson-media/')) {
            return storage_path('app/public/' . $path);
        }

        return storage_path('app/' . $path);
    }
}
