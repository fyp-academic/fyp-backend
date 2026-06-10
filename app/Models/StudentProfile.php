<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $table = 'student_profiles';

    // Table was created with only updated_at, not created_at
    public const CREATED_AT = null;

    protected $fillable = [
        'id', 'student_id', 'pace', 'quiz_average', 'weak_topics',
        'preferred_modality', 'completion_rate', 'profile_hash',
    ];

    protected function casts(): array
    {
        return [
            'weak_topics' => 'array',
            'quiz_average' => 'float',
            'completion_rate' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
