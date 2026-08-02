<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'user_id',
        'attempt_number',
        'status',
        'started_at',
        'deadline_at',
        'client_token',
        'finished_at',
        'score',
        'feedback',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'deadline_at' => 'datetime',
        'finished_at' => 'datetime',
        'attempt_number' => 'integer',
        'score' => 'decimal:2',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function answers()
    {
        return $this->hasMany(ExamAnswer::class);
    }
}
