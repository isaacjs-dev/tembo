<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityAttempt extends Model
{
    protected $fillable = ['organization_id', 'activity_id', 'student_id', 'attempt_number', 'status', 'content_snapshot', 'snapshot_hash', 'score', 'total_points', 'started_at', 'last_activity_at', 'submitted_at', 'graded_at', 'graded_by'];

    protected function casts(): array
    {
        return [
            'content_snapshot' => 'array', 'score' => 'decimal:2', 'total_points' => 'decimal:2',
            'started_at' => 'datetime', 'last_activity_at' => 'datetime', 'submitted_at' => 'datetime', 'graded_at' => 'datetime',
        ];
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class)->withTrashed();
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }

    public function responses()
    {
        return $this->hasMany(ActivityResponse::class);
    }

    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by')->withTrashed();
    }
}
