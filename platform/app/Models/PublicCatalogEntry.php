<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublicCatalogEntry extends Model
{
    protected $fillable = [
        'submission_id',
        'organization_id',
        'publisher_id',
        'entryable_type',
        'entryable_id',
        'fingerprint',
        'canonical_fingerprint',
        'status',
        'published_at',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'suspended_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PublicCatalogSubmission::class, 'submission_id');
    }

    public function entryable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PublicCatalogReport::class, 'public_catalog_entry_id');
    }
}
