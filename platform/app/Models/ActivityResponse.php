<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityResponse extends Model
{
    protected $fillable = ['activity_attempt_id', 'snapshot_question_key', 'question_id', 'answer', 'is_correct', 'points_awarded', 'feedback', 'answered_at'];

    protected function casts(): array
    {
        return ['answer' => 'array', 'is_correct' => 'boolean', 'points_awarded' => 'decimal:2', 'answered_at' => 'datetime'];
    }

    public function attempt()
    {
        return $this->belongsTo(ActivityAttempt::class, 'activity_attempt_id');
    }
}
