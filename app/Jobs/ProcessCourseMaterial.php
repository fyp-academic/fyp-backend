<?php

namespace App\Jobs;

use App\Jobs\ChunkContentJob;
use App\Models\CourseMaterial;
use App\Services\MaterialExtractorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCourseMaterial implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Retry up to 3 times with exponential backoff.
     */
    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(private CourseMaterial $material) {}

    /**
     * Extract text from the uploaded material and persist it.
     */
    public function handle(MaterialExtractorService $extractor): void
    {
        $this->material->update(['processing_status' => 'processing']);

        try {
            $text = match ($this->material->type) {
                'pdf'     => $extractor->extractPdf($this->material->file_path),
                'pptx'    => $extractor->extractPptx($this->material->file_path),
                'docx', 'doc' => $extractor->extractDocx($this->material->file_path),
                'video'   => $extractor->extractVideoMeta($this->material->file_path),
                'youtube' => $extractor->getYoutubeTranscript($this->material->url ?? ''),
                'h5p'     => $extractor->extractH5p($this->material->file_path),
                'scorm'   => $extractor->extractScorm($this->material->file_path),
                default   => null,
            };

            if ($text && strlen(trim($text)) >= 80) {
                $this->material->update([
                    'extracted_text'     => $text,
                    'processed_at'       => now(),
                    'word_count'         => str_word_count($text),
                    'processing_status'  => 'completed',
                    'processing_error'   => null,
                ]);

                ChunkContentJob::dispatch(
                    $this->material->id,
                    $text,
                    $this->material->type,
                    'course_material',
                );

                Log::info('Material processed and queued for chunking', [
                    'material_id' => $this->material->id,
                    'type'        => $this->material->type,
                    'word_count'  => str_word_count($text),
                ]);
            } else {
                $this->material->update([
                    'processing_status' => 'completed',
                    'processed_at'      => now(),
                ]);

                Log::info('Material processed — no extractable text', [
                    'material_id' => $this->material->id,
                    'type'        => $this->material->type,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Material processing failed', [
                'material_id' => $this->material->id,
                'error'       => $e->getMessage(),
            ]);

            $this->material->update([
                'processing_status' => 'failed',
                'processing_error'  => substr($e->getMessage(), 0, 500),
            ]);

            throw $e; // Let the queue system handle retries
        }
    }

    /**
     * Handle permanent failure after all retries exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Material processing permanently failed', [
            'material_id' => $this->material->id,
            'error'       => $exception->getMessage(),
        ]);

        $this->material->update([
            'processing_status' => 'failed',
            'processing_error'  => 'Permanent failure: ' . substr($exception->getMessage(), 0, 500),
        ]);
    }
}
