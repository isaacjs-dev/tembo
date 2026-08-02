<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmrScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'copy_id',
        'omr_template_id',
        'organization_id',
        'uploaded_by',
        'image_path',
        'warped_path',
        'debug_path',
        'idempotency_key',
        'status',
        'detected_answers',
        'confirmed_answers',
        'student_id',
        'exam_submission_id',
        'confidence_score',
        'notes',
        'source',
        'session_id',
        'layout_version',
        'total_pages',
        'raw_answers',
        'raw_confidences',
        'overall_confidence',
        'quality_json',
        'score',
        'total_points',
        'grading_details',
        'layout_meta',
    ];

    protected $casts = [
        'detected_answers' => 'array',
        'confirmed_answers' => 'array',
        'raw_answers' => 'array',
        'raw_confidences' => 'array',
        'grading_details' => 'array',
        'quality_json' => 'array',
        'confidence_score' => 'float',
        'overall_confidence' => 'float',
        'layout_meta' => 'array',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function submission()
    {
        return $this->belongsTo(ExamSubmission::class, 'exam_submission_id');
    }

    public function copy()
    {
        return $this->belongsTo(ExamCopy::class, 'copy_id');
    }

    public function omrTemplate()
    {
        return $this->belongsTo(OmrTemplate::class, 'omr_template_id');
    }

    public function pages()
    {
        // session_id matches, but since they may not always have a strict foreign key, we link via session_id
        return $this->hasMany(OmrScanPage::class, 'session_id', 'session_id')->orderBy('page_index');
    }
}
