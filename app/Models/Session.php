<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Session extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'video_sessions';

    protected $fillable = [
        'title',
        'course_id',
        'instructor_id',
        'room_id',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration',
        'max_participants',
        'password',
        'recording_enabled',
        'chat_enabled',
        'raise_hand_enabled',
        'waiting_room',
        'breakout_rooms',
        'screen_share_allowed',
        'start_muted',
        'start_video_off',
        'ai_transcription',
        'recording_url',
        'ai_summary',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'breakout_rooms' => 'array',
            'recording_enabled' => 'boolean',
            'chat_enabled' => 'boolean',
            'raise_hand_enabled' => 'boolean',
            'waiting_room' => 'boolean',
            'screen_share_allowed' => 'boolean',
            'start_muted' => 'boolean',
            'start_video_off' => 'boolean',
            'ai_transcription' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SessionParticipant::class);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(SessionTranscript::class)->orderBy('timestamp');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(SessionRecording::class);
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isEnded(): bool
    {
        return $this->status === 'ended';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function getFullTranscriptText(): string
    {
        return $this->transcripts
            ->map(fn ($t) => "[{$t->speaker_name}]: {$t->text}")
            ->join("\n");
    }
}
