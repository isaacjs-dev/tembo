<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublicCatalogReputationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event_key',
        'points',
        'rule_version',
        'idempotency_key',
        'source_type',
        'source_id',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
