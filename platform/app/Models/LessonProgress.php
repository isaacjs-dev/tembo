<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = ['organization_id', 'lesson_id', 'student_id', 'status', 'content_snapshot', 'snapshot_hash', 'started_at', 'last_activity_at', 'completed_at'];

    protected function casts(): array
    {
        return ['content_snapshot' => 'array', 'started_at' => 'datetime', 'last_activity_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function lesson()
    {
        return $this->belongsTo(Lesson::class)->withTrashed();
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id')->withTrashed();
    }
}
