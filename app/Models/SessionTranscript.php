<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionTranscript extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'video_session_transcripts';

    protected $fillable = [
        'session_id',
        'user_id',
        'text',
        'segments',
        'speaker_name',
        'timestamp',
    ];

    protected function casts(): array
    {
        return [
            'segments' => 'array',
            'timestamp' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
