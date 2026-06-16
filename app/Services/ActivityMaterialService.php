<?php

namespace App\Services;

use App\Jobs\ProcessCourseMaterial;
use App\Models\Activity;
use App\Models\CourseMaterial;
use Illuminate\Support\Str;

/**
 * Syncs uploaded activity assets (file, video, URL) into course_materials
 * so text can be extracted and chunked for personalization.
 */
class ActivityMaterialService
{
    /**
     * Ensure a course_material row exists for an activity; sync from settings if missing.
     */
    public function ensureForActivity(Activity $activity): ?CourseMaterial
    {
        $existing = CourseMaterial::where('activity_id', $activity->id)->first();
        if ($existing) {
            if ($existing->processing_status === 'pending' && ($existing->file_path || $existing->url)) {
                ProcessCourseMaterial::dispatch($existing);
            }

            return $existing;
        }

        return $this->syncFromActivitySettings($activity);
    }

    public function syncFromActivitySettings(Activity $activity, ?string $uploadedBy = null): ?CourseMaterial
    {
        $settings = $activity->settings ?? [];
        $payload = match ($activity->type) {
            'file' => $this->payloadFromFileSettings($activity, $settings, $uploadedBy),
            'video' => $this->payloadFromVideoSettings($activity, $settings, $uploadedBy),
            'url' => $this->payloadFromUrlSettings($activity, $settings, $uploadedBy),
            'h5p', 'scorm' => $this->payloadFromPackageSettings($activity, $settings, $uploadedBy),
            default => null,
        };

        if ($payload === null) {
            return null;
        }

        $material = CourseMaterial::firstOrNew(['activity_id' => $activity->id]);
        if (! $material->exists) {
            $material->id = (string) Str::uuid();
        }
        $material->fill($payload);
        $material->save();

        if ($material->wasRecentlyCreated || $material->processing_status === 'pending') {
            ProcessCourseMaterial::dispatch($material);
        }

        return $material;
    }

    public function syncFromUpload(
        Activity $activity,
        string $storagePath,
        string $fileName,
        string $mimeType,
        int $fileSize,
        string $materialType,
        ?string $uploadedBy = null,
    ): CourseMaterial {
        $material = CourseMaterial::firstOrNew(['activity_id' => $activity->id]);
        if (! $material->exists) {
            $material->id = (string) Str::uuid();
        }

        $material->fill([
            'course_id' => $activity->course_id,
            'uploaded_by' => $uploadedBy,
            'title' => $fileName ?: $activity->name,
            'type' => $materialType,
            'file_path' => $storagePath,
            'url' => null,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'processing_status' => 'pending',
            'processing_error' => null,
        ]);
        $material->save();

        ProcessCourseMaterial::dispatch($material);

        return $material;
    }

    public function detectMaterialType(string $mimeType, string $fileName): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $mime = strtolower($mimeType);

        if ($mime === 'application/pdf' || $ext === 'pdf') {
            return 'pdf';
        }
        if (in_array($ext, ['ppt', 'pptx'], true) || str_contains($mime, 'presentation')) {
            return 'pptx';
        }
        if (in_array($ext, ['doc', 'docx'], true) || str_contains($mime, 'wordprocessing')) {
            return 'docx';
        }
        if (str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'webm', 'mov', 'mkv'], true)) {
            return 'video';
        }
        if (in_array($ext, ['h5p'], true)) {
            return 'h5p';
        }
        if (in_array($ext, ['zip'], true) && str_contains(strtolower($fileName), 'scorm')) {
            return 'scorm';
        }

        return 'doc';
    }

    /** @return array<string, mixed>|null */
    private function payloadFromFileSettings(Activity $activity, array $settings, ?string $uploadedBy): ?array
    {
        $path = $settings['filePath'] ?? $settings['file_path'] ?? null;
        if (! $path) {
            return null;
        }

        $fileName = $settings['fileName'] ?? $activity->name;
        $mime = $settings['mimeType'] ?? 'application/octet-stream';

        return [
            'course_id' => $activity->course_id,
            'uploaded_by' => $uploadedBy,
            'title' => $fileName,
            'type' => $this->detectMaterialType($mime, $fileName),
            'file_path' => $path,
            'url' => null,
            'mime_type' => $mime,
            'file_size' => (int) ($settings['fileSize'] ?? 0),
            'processing_status' => 'pending',
        ];
    }

    /** @return array<string, mixed>|null */
    private function payloadFromVideoSettings(Activity $activity, array $settings, ?string $uploadedBy): ?array
    {
        $path = $settings['videoPath'] ?? $settings['video_path'] ?? null;
        $url = $settings['videoUrl'] ?? $settings['video_url'] ?? $settings['url'] ?? null;

        if (! $path && ! $url) {
            return null;
        }

        if (! $path && $url && $this->isYoutubeUrl($url)) {
            return [
                'course_id' => $activity->course_id,
                'uploaded_by' => $uploadedBy,
                'title' => $settings['fileName'] ?? $activity->name,
                'type' => 'youtube',
                'file_path' => null,
                'url' => $url,
                'mime_type' => 'video/youtube',
                'file_size' => 0,
                'processing_status' => 'pending',
            ];
        }

        if (! $path) {
            return null;
        }

        return [
            'course_id' => $activity->course_id,
            'uploaded_by' => $uploadedBy,
            'title' => $settings['fileName'] ?? $activity->name,
            'type' => 'video',
            'file_path' => $path,
            'url' => $url,
            'mime_type' => $settings['mimeType'] ?? 'video/mp4',
            'file_size' => (int) ($settings['fileSize'] ?? 0),
            'processing_status' => 'pending',
        ];
    }

    /** @return array<string, mixed>|null */
    private function payloadFromUrlSettings(Activity $activity, array $settings, ?string $uploadedBy): ?array
    {
        $url = $settings['url'] ?? $settings['fileUrl'] ?? $activity->settings['external_url'] ?? null;
        if (! $url || ! $this->isYoutubeUrl($url)) {
            return null;
        }

        return [
            'course_id' => $activity->course_id,
            'uploaded_by' => $uploadedBy,
            'title' => $activity->name,
            'type' => 'youtube',
            'file_path' => null,
            'url' => $url,
            'mime_type' => 'video/youtube',
            'file_size' => 0,
            'processing_status' => 'pending',
        ];
    }

    /**
     * H5P/SCORM are self-contained interactive packages — their archive contents
     * are structural JSON/HTML, not meaningful prose — so they are deliberately
     * excluded from text extraction / adaptive chunking.
     *
     * @return array<string, mixed>|null
     */
    private function payloadFromPackageSettings(Activity $activity, array $settings, ?string $uploadedBy): ?array
    {
        return null;
    }

    private function isYoutubeUrl(string $url): bool
    {
        return (bool) preg_match('/(?:youtube\.com|youtu\.be)/i', $url);
    }
}
