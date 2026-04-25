<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name', 'code', 'description',
    ];

    public function degreeProgrammes(): HasMany
    {
        return $this->hasMany(DegreeProgramme::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
