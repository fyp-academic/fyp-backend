<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiContentRecommendation extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'ai_content_recommendations';

    protected $fillable = [
        'id', 'course_id', 'title', 'content_type', 'relevance_score',
        'source', 'url', 'generated_at',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'date'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
