<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    use HasUuids;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'slug', 'name', 'description', 'icon', 'tier',
        'criteria_type', 'criteria_value', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['criteria_value' => 'float'];
    }
}
