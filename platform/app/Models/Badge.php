<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Badge extends Model
{
    protected $fillable = ['organization_id', 'key', 'name', 'description', 'icon', 'criteria', 'is_active'];

    protected function casts(): array
    {
        return ['criteria' => 'array', 'is_active' => 'boolean'];
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'student_badges', 'badge_id', 'student_id')->withPivot(['revision_attempt_id', 'awarded_at', 'metadata'])->withTimestamps();
    }
}
