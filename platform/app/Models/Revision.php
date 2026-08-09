<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Revision extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = ['organization_id', 'author_id', 'discipline_id', 'title', 'description', 'status', 'is_required', 'timing', 'block_exam', 'available_at', 'due_at', 'max_attempts', 'feedback_mode', 'gamification_enabled', 'published_at', 'reviewed_by', 'review_notes'];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'block_exam' => 'boolean', 'available_at' => 'datetime', 'due_at' => 'datetime', 'max_attempts' => 'integer', 'gamification_enabled' => 'boolean', 'published_at' => 'datetime'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'revision_school_class')->withTimestamps();
    }

    public function sources()
    {
        return $this->hasMany(RevisionSource::class);
    }

    public function items()
    {
        return $this->hasMany(RevisionItem::class)->orderBy('order');
    }

    public function activeItems()
    {
        return $this->hasMany(RevisionItem::class)->where('is_active', true)->orderBy('order');
    }

    public function attempts()
    {
        return $this->hasMany(RevisionAttempt::class);
    }

    public function imports()
    {
        return $this->hasMany(RevisionImport::class);
    }
}
