<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_submission_id',
        'question_id',
        'answer_data',
        'is_correct',
        'points_awarded',
        'feedback',
        'grading_justification',
        'rubric_scores',
    ];

    protected $casts = [
        'answer_data' => 'array',
        'is_correct' => 'boolean',
        'points_awarded' => 'decimal:2',
        'rubric_scores' => 'array',
    ];

    public function submission()
    {
        return $this->belongsTo(ExamSubmission::class, 'exam_submission_id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
