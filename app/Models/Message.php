<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'conversation_id', 'sender_id', 'sender_name',
        'content', 'timestamp', 'read',
        'attachment_path', 'attachment_name', 'attachment_type', 'attachment_size',
        'reactions',
    ];

    protected function casts(): array
    {
        return [
            'read'            => 'boolean',
            'timestamp'       => 'datetime',
            'reactions'       => 'array',
            'attachment_size' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
