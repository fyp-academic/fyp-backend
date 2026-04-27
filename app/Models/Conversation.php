<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Conversation extends Model
{
    use SoftDeletes;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'type', 'owner_user_id', 'participant_user_id', 'participant_name',
        'participant_role', 'last_message', 'last_message_time',
        'unread_count', 'course_id', 'programme_id', 'title',
        'is_moderated', 'is_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_moderated' => 'boolean',
            'is_locked' => 'boolean',
            'last_message_time' => 'datetime',
        ];
    }

    // Chat types
    public const TYPE_DIRECT = 'direct';
    public const TYPE_COURSE = 'course';
    public const TYPE_PROGRAMME = 'programme';

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function degreeProgramme(): BelongsTo
    {
        return $this->belongsTo(DegreeProgramme::class, 'programme_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['role', 'joined_at', 'last_read_at', 'unread_count'])
            ->withTimestamps();
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ChatReport::class);
    }

    /**
     * Check if user is a participant in this conversation
     */
    public function hasParticipant(string $userId): bool
    {
        return $this->participants()->where('user_id', $userId)->exists();
    }

    /**
     * Check if user is an admin/moderator in this conversation
     */
    public function isModerator(string $userId): bool
    {
        $participant = $this->participants()->where('user_id', $userId)->first();
        return $participant && in_array($participant->role, ['admin', 'moderator']);
    }

    /**
     * Check if this is a direct message conversation
     */
    public function isDirect(): bool
    {
        return $this->type === self::TYPE_DIRECT;
    }

    /**
     * Check if this is a course chat
     */
    public function isCourseChat(): bool
    {
        return $this->type === self::TYPE_COURSE;
    }

    /**
     * Check if this is a programme chat
     */
    public function isProgrammeChat(): bool
    {
        return $this->type === self::TYPE_PROGRAMME;
    }
}
