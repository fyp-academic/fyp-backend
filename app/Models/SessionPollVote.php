<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionPollVote extends Model
{
    protected $table = 'video_session_poll_votes';
    
    protected $fillable = [
        'poll_id',
        'user_id',
        'option_index',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(SessionPoll::class, 'poll_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
