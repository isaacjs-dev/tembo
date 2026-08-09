<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentGamificationProfile extends Model
{
    protected $fillable = ['organization_id', 'student_id', 'xp', 'level', 'current_streak', 'longest_streak', 'last_study_date'];

    protected function casts(): array
    {
        return ['last_study_date' => 'date'];
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
