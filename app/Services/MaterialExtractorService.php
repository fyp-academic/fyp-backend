<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\Shape\RichText;

class MaterialExtractorService
{
    /**
     * Maximum characters to extract — keeps Gemini token usage within budget.
     */
    private const MAX_CHARS = 8000;

    // ── PDF ──────────────────────────────────────────────────────────

    public function extractPdf(string $path): string
    {
        try {
            $parser = new PdfParser();
            $pdf    = $parser->parseFile($this->resolveStoragePath($path));
            $text   = $pdf->getText();

            return $this->truncate($text);
        } catch (\Throwable $e) {
            Log::warning('PDF extraction failed', ['path' => $path, 'error' => $e->getMessage()]);
            return '';
        }
    }

    // ── PowerPoint (PPTX) ────────────────────────────────────────────

    public function extractPptx(string $path): string
    {
        try {
            $presentation = IOFactory::load($this->resolveStoragePath($path));
            $text         = '';

            foreach ($presentation->getAllSlides() as $i => $slide) {
                $text .= 'Slide ' . ($i + 1) . ":\n";

                foreach ($slide->getShapeCollection() as $shape) {
                    if ($shape instanceof RichText) {
                        foreach ($shape->getParagraphs() as $para) {
                            $line = $para->getPlainText();
                            if (trim($line) !== '') {
                                $text .= $line . "\n";
                            }
                        }
                    }
                }
                $text .= "\n";
            }

            return $this->truncate($text);
        } catch (\Throwable $e) {
            Log::warning('PPTX extraction failed', ['path' => $path, 'error' => $e->getMessage()]);
            return '';
        }
    }

    // ── H5P (interactive content) ────────────────────────────────────

    public function extractH5p(string $path): string
    {
        try {
            $zip     = new \ZipArchive();
            $tmpPath = sys_get_temp_dir() . '/h5p_' . uniqid();

            if ($zip->open($this->resolveStoragePath($path)) !== true) {
                return '';
            }

            $zip->extractTo($tmpPath);
            $zip->close();

            $contentFile = $tmpPath . '/content/content.json';
            if (!file_exists($contentFile)) {
                $this->cleanupTemp($tmpPath);
                return '';
            }

            $content = json_decode(file_get_contents($contentFile), true);
            $text    = $this->flattenJson($content);

            $this->cleanupTemp($tmpPath);

            return $this->truncate($text);
        } catch (\Throwable $e) {
            Log::warning('H5P extraction failed', ['path' => $path, 'error' => $e->getMessage()]);
            return '';
        }
    }

    // ── SCORM ────────────────────────────────────────────────────────

    public function extractScorm(string $path): string
    {
        try {
            $zip     = new \ZipArchive();
            $tmpPath = sys_get_temp_dir() . '/scorm_' . uniqid();

            if ($zip->open($this->resolveStoragePath($path)) !== true) {
                return '';
            }

            $zip->extractTo($tmpPath);
            $zip->close();

            $text = '';

            // Recursively find HTML files in the extracted SCORM package
            $htmlFiles = $this->findFiles($tmpPath, '*.html');
            foreach ($htmlFiles as $file) {
                $html  = file_get_contents($file);
                $text .= strip_tags($html) . "\n";
            }

            $this->cleanupTemp($tmpPath);

            return $this->truncate($text);
        } catch (\Throwable $e) {
            Log::warning('SCORM extraction failed', ['path' => $path, 'error' => $e->getMessage()]);
            return '';
        }
    }

    // ── Word DOCX ────────────────────────────────────────────────────

    public function extractDocx(string $path): string
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($this->resolveStoragePath($path)) !== true) {
                return '';
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xml === false) {
                return '';
            }

            $text = strip_tags(str_replace(['</w:p>', '<w:tab/>'], ["\n", ' '], $xml));

            return $this->truncate($text);
        } catch (\Throwable $e) {
            Log::warning('DOCX extraction failed', ['path' => $path, 'error' => $e->getMessage()]);
            return '';
        }
    }

    // ── Video metadata (local files) ─────────────────────────────────

    public function extractVideoMeta(string $path): string
    {
        // For locally uploaded videos we store the filename as a minimal reference.
        // Full transcript extraction would require Whisper / a speech-to-text service.
        $filename = basename($path);
        return "Video file: {$filename}";
    }

    // ── YouTube transcript / description ─────────────────────────────

    public function getYoutubeTranscript(string $url): string
    {
        try {
            preg_match('/(?:v=|youtu\.be\/)([^&\s]+)/', $url, $matches);
            $videoId = $matches[1] ?? null;

            if (!$videoId) {
                return '';
            }

            $apiKey = config('services.youtube.api_key');

            if (empty($apiKey)) {
                Log::warning('YouTube API key not configured');
                return "YouTube Video ID: {$videoId}";
            }

            $resp = Http::timeout(10)->get(
                'https://www.googleapis.com/youtube/v3/videos',
                ['part' => 'snippet', 'id' => $videoId, 'key' => $apiKey]
            );

            if ($resp->failed()) {
                Log::warning('YouTube API request failed', ['status' => $resp->status()]);
                return "YouTube Video ID: {$videoId}";
            }

            $item = $resp->json('items.0.snippet', []);
            $title       = $item['title'] ?? 'Unknown';
            $description = $item['description'] ?? '';

            return $this->truncate("Title: {$title}\nDescription: {$description}");
        } catch (\Throwable $e) {
            Log::warning('YouTube transcript failed', ['url' => $url, 'error' => $e->getMessage()]);
            return '';
        }
    }

    // ── Private helpers ──────────────────────────────────────────────

    /**
     * Recursively extract text values from a JSON structure (H5P content.json).
     */
    private function flattenJson(mixed $data, int $depth = 0): string
    {
        if ($depth > 5 || !is_array($data)) {
            return '';
        }

        $text = '';
        foreach ($data as $value) {
            if (is_string($value) && trim($value) !== '') {
                $text .= strip_tags($value) . ' ';
            } elseif (is_array($value)) {
                $text .= $this->flattenJson($value, $depth + 1);
            }
        }

        return $text;
    }

    /**
     * Recursively find files matching a glob pattern inside a directory.
     */
    private function findFiles(string $dir, string $pattern): array
    {
        $files   = glob($dir . '/' . $pattern) ?: [];
        $subdirs = glob($dir . '/*', GLOB_ONLYDIR) ?: [];

        foreach ($subdirs as $subdir) {
            $files = array_merge($files, $this->findFiles($subdir, $pattern));
        }

        return $files;
    }

    /**
     * Remove a temporary directory and its contents.
     */
    private function cleanupTemp(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($path);
    }

    /**
     * Truncate text to MAX_CHARS to keep Gemini token budget in check.
     */
    private function truncate(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', trim($text));
        return substr($text, 0, self::MAX_CHARS);
    }

    private function resolveStoragePath(string $path): string
    {
        if (str_starts_with($path, 'public/')) {
            return storage_path('app/'.$path);
        }

        if (str_starts_with($path, 'files/') || str_starts_with($path, 'videos/')
            || str_starts_with($path, 'scorm/') || str_starts_with($path, 'h5p/')) {
            return storage_path('app/public/'.$path);
        }

        return storage_path('app/'.$path);
    }
}
