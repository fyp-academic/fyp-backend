<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookChapter extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'activity_id', 'title', 'content',
        'sort_order', 'sub_chapter', 'hidden',
    ];

    protected function casts(): array
    {
        return [
            'sub_chapter' => 'boolean',
            'hidden'      => 'boolean',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
