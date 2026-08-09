<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LearningMaterial extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'author_id',
        'title',
        'description',
        'body',
        'external_url',
        'discipline_id',
        'custom_skill_id',
        'bncc_node_id',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function customSkill(): BelongsTo
    {
        return $this->belongsTo(CustomSkill::class);
    }

    public function bnccNode(): BelongsTo
    {
        return $this->belongsTo(BNCcNode::class);
    }

    public function schoolClasses(): BelongsToMany
    {
        return $this->belongsToMany(
            SchoolClass::class,
            'learning_material_school_class'
        )->withTimestamps();
    }

    public function progressRecords(): HasMany
    {
        return $this->hasMany(LearningMaterialProgress::class);
    }

    public function revisionSources()
    {
        return $this->morphMany(RevisionSource::class, 'source');
    }
}
