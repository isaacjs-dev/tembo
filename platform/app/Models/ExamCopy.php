<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamCopy extends Model
{
    protected $fillable = [
        'exam_id',
        'school_class_id',
        'student_id',
        'generation_uuid',
        'copy_number',
        'exam_version',
        'card_template_id',
        'card_template_version',
        'output_type',
        'template_snapshot',
        'questions_map',
        'options_map',
        'question_snapshot',
        'validation_hash',
    ];

    protected function casts(): array
    {
        return [
            'questions_map' => 'array',
            'options_map' => 'array',
            'question_snapshot' => 'array',
            'template_snapshot' => 'array',
            'exam_version' => 'integer',
            'card_template_version' => 'integer',
        ];
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function cardTemplate()
    {
        return $this->belongsTo(OmrTemplate::class, 'card_template_id');
    }
}
