<?php

namespace App\Jobs;

use App\Jobs\ChunkContentJob;
use App\Models\CourseMaterial;
use App\Services\MaterialExtractorService;
use App\Services\VideoTranscriptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCourseMaterial implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const MIN_EXTRACTED_TEXT_LENGTH = 80;

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
    public function handle(MaterialExtractorService $extractor, VideoTranscriptService $videoTranscriptService): void
    {
        $this->material->update(['processing_status' => 'processing']);

        try {
            $this->material->loadMissing(['course', 'activity.section']);

            $text = match ($this->material->type) {
                'pdf'     => $extractor->extractPdf($this->material->file_path),
                'pptx'    => $extractor->extractPptx($this->material->file_path),
                'docx', 'doc' => $extractor->extractDocx($this->material->file_path),
                'video'   => $videoTranscriptService->transcribeLocalVideo(
                    $this->material->file_path ?? '',
                    $this->material->url,
                    $this->material->title,
                ),
                'youtube' => $videoTranscriptService->transcribeYouTube(
                    $this->material->url ?? '',
                    $this->material->title,
                ),
                'h5p'     => $extractor->extractH5p($this->material->file_path),
                'scorm'   => $extractor->extractScorm($this->material->file_path),
                default   => null,
            };

            $text = is_string($text) ? trim($text) : '';
            $processingError = null;

            if ($text !== '' && strlen($text) >= self::MIN_EXTRACTED_TEXT_LENGTH) {
                if (in_array($this->material->type, ['video', 'youtube'], true)) {
                    $relevance = $videoTranscriptService->assessCourseRelevance($text, [
                        'course_name' => $this->material->course?->name,
                        'section_name' => $this->material->activity?->section?->title ?? $this->material->activity?->section?->name ?? null,
                        'activity_name' => $this->material->activity?->name ?? $this->material->title,
                    ]);

                    if (! $relevance['is_relevant'] && $relevance['confidence'] >= 0.55) {
                        $processingError = 'content_mismatch: The extracted video content appears unrelated to this course activity. Personalization is paused until the resource is reviewed.';
                    }
                }
            } else {
                $processingError = $this->insufficientExtractionReason($text);
            }

            if ($processingError === null && $text !== '' && strlen($text) >= self::MIN_EXTRACTED_TEXT_LENGTH) {
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
                    'extracted_text'    => null,
                    'word_count'        => 0,
                    'processing_error'  => $processingError,
                ]);

                Log::info('Material processed — no extractable text', [
                    'material_id' => $this->material->id,
                    'type'        => $this->material->type,
                    'reason'      => $processingError,
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

    private function insufficientExtractionReason(string $text): string
    {
        if ($this->material->type === 'youtube') {
            return 'transcript_unavailable: We could not obtain enough verified spoken content from this YouTube video to generate a reliable personalized guide.';
        }

        if ($this->material->type === 'video') {
            if (($this->material->file_size ?? 0) > 18_000_000) {
                return 'transcript_unavailable: This uploaded video is currently too large for direct transcript extraction in the personalization pipeline.';
            }

            if (str_starts_with($text, 'Video file:')) {
                return 'transcript_unavailable: Only basic file metadata was available, so a reliable transcript could not be generated for personalization.';
            }

            return 'transcript_unavailable: We could not extract enough spoken teaching content from this video to personalize it safely.';
        }

        return 'extract_unavailable: Not enough usable text was available to generate a personalized guide.';
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
