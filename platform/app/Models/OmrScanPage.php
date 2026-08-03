<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OmrScanPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'session_id',
        'exam_id',
        'copy_id',
        'student_id',
        'uploaded_by',
        'page_index',
        'total_pages',
        'image_path',
        'qr_payload',
        'raw_answers',
        'raw_confidences',
        'overall_confidence',
        'status',
    ];

    protected $casts = [
        'raw_answers' => 'array',
        'qr_payload' => 'array',
        'raw_confidences' => 'array',
        'overall_confidence' => 'float',
        'page_index' => 'integer',
        'total_pages' => 'integer',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
