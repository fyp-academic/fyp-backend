<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumPostReaction extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'post_id', 'user_id', 'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
