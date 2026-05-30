<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ContentChunk;
use App\Models\CourseMaterial;
use App\Models\LessonPage;

class AdaptableContentResolver
{
    public function __construct(
        private ActivityMaterialService $materialService,
    ) {}

    /**
     * @return array{course_id: string|null, section_id: string|null}
     */
    public function courseContextForChunk(ContentChunk $chunk): array
    {
        if ($chunk->content_source === 'course_material') {
            $material = CourseMaterial::with('activity.section')->find($chunk->content_id);
            if ($material?->activity) {
                return [
                    'course_id' => $material->course_id,
                    'section_id' => $material->activity->section_id,
                ];
            }
        }

        $page = LessonPage::with('activity.section')->find($chunk->content_id);
        if ($page?->activity) {
            return [
                'course_id' => $page->activity->course_id,
                'section_id' => $page->activity->section_id,
            ];
        }

        return ['course_id' => null, 'section_id' => null];
    }

    /**
     * @return array{chunks: \Illuminate\Support\Collection, material: CourseMaterial|null, status: string}
     */
    public function chunksForActivity(Activity $activity): array
    {
        $material = $this->materialService->ensureForActivity($activity);

        $pageIds = LessonPage::where('activity_id', $activity->id)->pluck('id');
        $lessonChunks = ContentChunk::where('content_source', 'lesson_page')
            ->whereIn('content_id', $pageIds)
            ->orderBy('content_id')
            ->orderBy('chunk_index')
            ->get(['id', 'chunk_index', 'chunk_text', 'chunk_type', 'content_id', 'content_source']);

        $materialChunks = collect();
        if ($material) {
            $materialChunks = ContentChunk::where('content_source', 'course_material')
                ->where('content_id', $material->id)
                ->orderBy('chunk_index')
                ->get(['id', 'chunk_index', 'chunk_text', 'chunk_type', 'content_id', 'content_source']);
        }

        $chunks = $lessonChunks->concat($materialChunks)->values();

        $status = 'none';
        if ($material) {
            $status = $material->processing_status;
            if ($status === 'completed' && $materialChunks->isEmpty() && ! $material->hasExtractedText()) {
                $status = match (true) {
                    str_starts_with((string) $material->processing_error, 'content_mismatch:') => 'content_mismatch',
                    str_starts_with((string) $material->processing_error, 'transcript_unavailable:') => 'transcript_unavailable',
                    default => 'no_extractable_text',
                };
            }
        }

        return [
            'chunks' => $chunks,
            'material' => $material,
            'status' => $chunks->isNotEmpty() ? 'ready' : $status,
        ];
    }
}
