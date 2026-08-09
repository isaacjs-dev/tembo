<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminBatchOperation extends Model
{
    protected $fillable = [
        'operation_type', 'target_scope', 'target_id', 'target_role', 'resource_keys',
        'status', 'selected_count', 'processed_count', 'skipped_count', 'failed_count',
        'requested_by', 'reason', 'result', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['resource_keys' => 'array', 'result' => 'array', 'completed_at' => 'datetime'];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
