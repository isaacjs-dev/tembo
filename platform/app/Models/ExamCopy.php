<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamCopy extends Model
{
    protected $fillable = [
        'exam_id',
        'school_class_id',
        'copy_number',
        'questions_map',
        'options_map',
        'validation_hash',
    ];

    protected function casts(): array
    {
        return [
            'questions_map' => 'array',
            'options_map' => 'array',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
