<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SessionPoll extends Model
{
    protected $table = 'video_session_polls';
    
    protected $fillable = [
        'session_id',
        'question',
        'options',
        'is_active',
        'is_multiple_choice',
        'created_by',
        'ended_at',
    ];

    protected $casts = [
        'options' => 'array',
        'is_active' => 'boolean',
        'is_multiple_choice' => 'boolean',
        'ended_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class, 'session_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(SessionPollVote::class, 'poll_id');
    }

    public function getResults(): array
    {
        $votes = $this->votes;
        $results = [];
        
        foreach ($this->options as $index => $option) {
            $count = $votes->where('option_index', $index)->count();
            $results[] = [
                'option' => $option,
                'count' => $count,
                'percentage' => $votes->count() > 0 ? round(($count / $votes->count()) * 100, 1) : 0,
            ];
        }
        
        return [
            'total_votes' => $votes->count(),
            'options' => $results,
        ];
    }
}
