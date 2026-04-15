<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRecommendation extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';
    protected $table     = 'ai_recommendations';

    protected $fillable = [
        'id', 'course_id', 'title', 'description', 'impact_level',
        'icon_name', 'color_scheme', 'generated_at',
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
