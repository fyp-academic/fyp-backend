<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SessionRecording extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'video_session_recordings';

    protected $fillable = [
        'session_id',
        's3_key',
        'duration',
        'size',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'size' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function getDownloadUrl(): string
    {
        $disk = Storage::disk('s3');

        // Fallback to regular URL if temporaryUrl is not supported (local disk) or S3 is not configured
        if (!method_exists($disk, 'temporaryUrl')) {
            if (!method_exists($disk, 'url')) {
                throw new \RuntimeException('Storage disk does not support URL generation');
            }
            return $disk->url($this->s3_key);
        }

        // Generate signed S3 URL
        return $disk->temporaryUrl(
            $this->s3_key,
            now()->addMinutes(60)
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted(int $duration, int $size): void
    {
        $this->update([
            'status' => 'completed',
            'duration' => $duration,
            'size' => $size,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => 'failed']);
    }
}
