<?php

namespace App\Models;

use App\Models\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuestionResource extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    public const TYPES = ['text', 'image', 'chart', 'table', 'formula', 'diagram', 'document'];

    public const VISIBILITY_SCOPES = ['private', 'shared_specific', 'organization', 'platform_public'];

    protected $fillable = [
        'organization_id',
        'owner_id',
        'source_resource_id',
        'title',
        'type',
        'visibility_scope',
        'status',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function sourceResource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_resource_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(QuestionResourceVersion::class)->orderByDesc('version_number');
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(QuestionResourceVersion::class)->ofMany('version_number', 'max');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(QuestionResourceShare::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'question_question_resource')
            ->using(QuestionResourceLink::class)
            ->withPivot(['id', 'question_resource_version_id', 'is_required', 'sort_order'])
            ->withTimestamps();
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
}
