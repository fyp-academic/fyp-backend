<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateIssue extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'certificate_id', 'student_id',
        'issued_at', 'code', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'  => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
