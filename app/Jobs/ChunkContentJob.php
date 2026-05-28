<?php

namespace App\Jobs;

use App\Services\ContentChunkingService;
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
    ) {}

    public function handle(ContentChunkingService $chunkingService): void
    {
        try {
            $chunkIds = $chunkingService->chunk($this->contentId, $this->contentText, $this->contentType);

            Log::info('Content chunked successfully', [
                'content_id' => $this->contentId,
                'chunks_created' => count($chunkIds),
            ]);
        } catch (\Throwable $e) {
            Log::error('Content chunking failed', [
                'content_id' => $this->contentId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ChunkContentJob permanently failed', [
            'content_id' => $this->contentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
