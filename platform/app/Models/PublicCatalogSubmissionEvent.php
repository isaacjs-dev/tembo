<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicCatalogSubmissionEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'reason',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(PublicCatalogSubmission::class, 'submission_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
