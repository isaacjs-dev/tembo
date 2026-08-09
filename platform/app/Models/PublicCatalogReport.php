<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicCatalogReport extends Model
{
    protected $fillable = [
        'organization_id',
        'reporter_id',
        'public_catalog_entry_id',
        'reason_code',
        'details',
        'status',
        'idempotency_key',
        'moderator_id',
        'resolution',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PublicCatalogEntry::class, 'public_catalog_entry_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_id');
    }
}
