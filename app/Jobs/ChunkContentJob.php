<?php

namespace App\Jobs;

use App\Services\ContentChunkingService;
use App\Services\VideoTranscriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ChunkContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private string $contentId,
        private string $contentText,
        private string $contentType = 'lecture',
        private string $contentSource = 'lesson_page',
    ) {}

    public function handle(
        ContentChunkingService $chunkingService,
        VideoTranscriptService $videoTranscriptService,
    ): void
    {
        $contentText = $this->contentText;

        if ($this->contentSource === 'lesson_page') {
            $contentText = $videoTranscriptService->enrichLessonPageHtml($contentText);
        }

        if (trim($contentText) === '') {
            Log::info('Content chunking skipped — empty text', ['content_id' => $this->contentId]);

            return;
        }

        try {
            $chunkIds = $chunkingService->chunk(
                $this->contentId,
                $contentText,
                $this->contentType,
                $this->contentSource,
            );

            Log::info('Content chunked successfully', [
                'content_id' => $this->contentId,
                'content_source' => $this->contentSource,
                'chunks_created' => count($chunkIds),
            ]);
        } catch (\Throwable $e) {
            Log::error('Content chunking failed', [
                'content_id' => $this->contentId,
                'content_source' => $this->contentSource,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ChunkContentJob permanently failed', [
            'content_id' => $this->contentId,
            'content_source' => $this->contentSource,
            'error' => $exception->getMessage(),
        ]);
    }
}
