<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Question extends Model
{
    use HasFactory, SoftDeletes;

    public const VISIBILITY_SCOPES = ['private', 'shared_specific', 'org_public', 'platform_public'];

    protected $fillable = [
        'organization_id',
        'owner_id',
        'type',
        'content',
        'visibility_scope',
        'source_question_id',
        'knowledge_area_id',
        'discipline_id',
        'level',
        'stage',
        'grade',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sourceQuestion()
    {
        return $this->belongsTo(Question::class, 'source_question_id');
    }

    public function shares()
    {
        return $this->hasMany(QuestionShare::class);
    }

    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(QuestionResource::class, 'question_question_resource')
            ->using(QuestionResourceLink::class)
            ->withPivot(['id', 'question_resource_version_id', 'is_required', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function resourceLinks(): HasMany
    {
        return $this->hasMany(QuestionResourceLink::class)->orderBy('sort_order');
    }

    public function publicCatalogSubmissions(): MorphMany
    {
        return $this->morphMany(PublicCatalogSubmission::class, 'submittable');
    }

    public function publicCatalogEntries(): MorphMany
    {
        return $this->morphMany(PublicCatalogEntry::class, 'entryable');
    }

    public function hasActivePublicSubmission(): bool
    {
        if (array_key_exists('has_active_public_submission', $this->attributes)) {
            return (bool) $this->getAttribute('has_active_public_submission');
        }

        return $this->publicCatalogSubmissions()
            ->whereIn('status', PublicCatalogSubmission::ACTIVE_STATUSES)
            ->exists();
    }

    public function knowledgeArea()
    {
        return $this->belongsTo(KnowledgeArea::class);
    }

    public function discipline()
    {
        return $this->belongsTo(Discipline::class);
    }

    public function bnccSkills()
    {
        return $this->belongsToMany(BNCcNode::class, 'question_bncc_links', 'question_id', 'bncc_skill_node_id')->withTimestamps();
    }

    public function customSkills()
    {
        return $this->belongsToMany(CustomSkill::class, 'question_custom_skill', 'question_id', 'custom_skill_id')->withTimestamps();
    }

    public function revisionSources()
    {
        return $this->morphMany(RevisionSource::class, 'source');
    }
}
