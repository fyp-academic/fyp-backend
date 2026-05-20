<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionParticipant extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'video_session_participants';

    protected $fillable = [
        'session_id',
        'user_id',
        'joined_at',
        'left_at',
        'mic_active',
        'camera_active',
        'hands_raised',
        'chat_messages',
        'attendance_score',
        'join_punctuality_minutes',
        'poll_responses_count',
        'participation_duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'mic_active' => 'boolean',
            'camera_active' => 'boolean',
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

    public function calculateAttendanceScore(): int
    {
        $score = 0;

        // Joined on time (20 points)
        if ($this->joined_at && $this->session) {
            $scheduledTime = $this->session->scheduled_at;
            if ($this->joined_at->diffInMinutes($scheduledTime) <= 5) {
                $score += 20;
            } elseif ($this->joined_at->diffInMinutes($scheduledTime) <= 15) {
                $score += 10;
            }
        }

        // Camera time (20 points)
        if ($this->camera_active) {
            $score += 20;
        }

        // Chat participation (20 points)
        $chatScore = min($this->chat_messages * 5, 20);
        $score += $chatScore;

        // Hand raises (20 points)
        $handScore = min($this->hands_raised * 10, 20);
        $score += $handScore;

        // Mic activity (20 points)
        if ($this->mic_active) {
            $score += 20;
        }

        return min($score, 100);
    }

    public function updateAttendanceScore(): void
    {
        $this->attendance_score = $this->calculateAttendanceScore();
        $this->save();
    }
}
