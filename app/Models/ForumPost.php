<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForumPost extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'discussion_id', 'user_id', 'parent_id',
        'subject', 'content', 'attachment_path',
        'likes_count', 'dislikes_count', 'anonymous', 'quality_score', 'sentiment', 'depth_level',
    ];

    protected function casts(): array
    {
        return [
            'likes_count'    => 'integer',
            'dislikes_count' => 'integer',
            'anonymous'      => 'boolean',
            'quality_score'  => 'float',
            'depth_level'    => 'integer',
        ];
    }

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(ForumDiscussion::class, 'discussion_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(ForumPost::class, 'parent_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ForumPostReaction::class, 'post_id');
    }
}
