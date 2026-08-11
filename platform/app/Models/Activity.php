<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['organization_id', 'author_id', 'discipline_id', 'title', 'instructions', 'available_at', 'due_at', 'max_attempts', 'points', 'modality', 'status', 'generate_review', 'published_at'];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'due_at' => 'datetime', 'max_attempts' => 'integer', 'points' => 'decimal:2', 'generate_review' => 'boolean', 'published_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'activity_school_class')->withTimestamps();
    }

    public function questions()
    {
        return $this->belongsToMany(Question::class, 'activity_question')->withPivot(['points', 'order'])->withTimestamps()->orderBy('activity_question.order');
    }

    public function revisionSources()
    {
        return $this->morphMany(RevisionSource::class, 'source');
    }

    public function attempts()
    {
        return $this->hasMany(ActivityAttempt::class);
    }
}
