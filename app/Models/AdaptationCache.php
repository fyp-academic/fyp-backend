<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Persistent shared adaptation cache — one generated copy per (chunk, level-signature,
 * cache_version), reused forever across same-level learners. See the migration for rationale.
 */
class AdaptationCache extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'adaptation_cache';

    protected $fillable = [
        'id', 'chunk_id', 'signature', 'cache_version', 'content_hash',
        'adapted_text', 'delivery_status', 'similarity_percent', 'integrity', 'adaptation_id',
    ];

    protected function casts(): array
    {
        return [
            'integrity' => 'array',
            'similarity_percent' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
