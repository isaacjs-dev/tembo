<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BNCcNode extends Model
{
    protected $table = 'bncc_nodes';

    protected $fillable = [
        'discipline_id',
        'stage',
        'grade',
        'type',
        'code',
        'title',
        'description',
        'parent_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(BNCcNode::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(BNCcNode::class, 'parent_id');
    }
}
