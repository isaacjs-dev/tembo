<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionResponse extends Model
{
    protected $fillable = ['revision_attempt_id', 'revision_item_id', 'snapshot_item_key', 'answer', 'is_correct', 'points_awarded', 'response_time_seconds', 'item_snapshot', 'feedback', 'answered_at'];

    protected function casts(): array
    {
        return ['answer' => 'array', 'is_correct' => 'boolean', 'points_awarded' => 'decimal:2', 'item_snapshot' => 'array', 'answered_at' => 'datetime'];
    }

    public function attempt()
    {
        return $this->belongsTo(RevisionAttempt::class, 'revision_attempt_id');
    }

    public function item()
    {
        return $this->belongsTo(RevisionItem::class, 'revision_item_id');
    }
}
