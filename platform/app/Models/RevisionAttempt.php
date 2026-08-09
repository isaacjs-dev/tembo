<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionAttempt extends Model
{
    protected $fillable = ['revision_id', 'student_id', 'organization_id', 'attempt_number', 'status', 'current_position', 'score', 'total_points', 'xp_earned', 'started_at', 'last_activity_at', 'completed_at'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'last_activity_at' => 'datetime', 'completed_at' => 'datetime', 'score' => 'decimal:2', 'total_points' => 'decimal:2'];
    }

    public function revision()
    {
        return $this->belongsTo(Revision::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function responses()
    {
        return $this->hasMany(RevisionResponse::class);
    }
}
