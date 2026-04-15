<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentGrade extends Model
{
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'grade_item_id', 'student_id', 'student_name',
        'grade', 'percentage', 'feedback', 'submitted_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'submitted_date' => 'date',
        ];
    }

    public function gradeItem(): BelongsTo
    {
        return $this->belongsTo(GradeItem::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
