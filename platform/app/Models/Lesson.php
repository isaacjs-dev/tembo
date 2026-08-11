<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lesson extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['organization_id', 'author_id', 'discipline_id', 'title', 'objectives', 'content', 'attachments', 'starts_at', 'status', 'generate_review', 'published_at'];

    protected function casts(): array
    {
        return ['attachments' => 'array', 'starts_at' => 'datetime', 'generate_review' => 'boolean', 'published_at' => 'datetime'];
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
        return $this->belongsToMany(SchoolClass::class, 'lesson_school_class')->withTimestamps();
    }

    public function revisionSources()
    {
        return $this->morphMany(RevisionSource::class, 'source');
    }

    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }
}
