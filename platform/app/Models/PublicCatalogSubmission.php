<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublicCatalogSubmission extends Model
{
    public const ACTIVE_STATUSES = ['pending', 'in_review'];

    public const TERMINAL_STATUSES = ['approved', 'rejected', 'withdrawn', 'removed'];

    protected $fillable = [
        'organization_id',
        'submitter_id',
        'submittable_type',
        'submittable_id',
        'previous_submission_id',
        'status',
        'content_hash',
        'active_fingerprint',
        'similarity_key',
        'snapshot_json',
        'rights_basis',
        'rights_notes',
        'terms_version',
        'attribution',
        'evidence_url',
        'rights_confirmed_at',
        'idempotency_key',
        'duplicate_candidates_json',
        'duplicate_of_submission_id',
        'moderator_id',
        'decision_reason',
        'submitted_at',
        'review_started_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_json' => 'array',
            'duplicate_candidates_json' => 'array',
            'rights_confirmed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'review_started_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function submittable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PublicCatalogSubmissionEvent::class, 'submission_id')->orderBy('created_at');
    }

    public function entry()
    {
        return $this->hasOne(PublicCatalogEntry::class, 'submission_id');
    }

    public function rewardAward()
    {
        return $this->hasOne(PublicCatalogRewardAward::class, 'public_catalog_submission_id');
    }

    public function previousSubmission(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_submission_id');
    }
}
