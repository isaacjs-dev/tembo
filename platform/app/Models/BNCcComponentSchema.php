<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BNCcComponentSchema extends Model
{
    protected $table = 'bncc_component_schemas';

    protected $fillable = [
        'discipline_id',
        'stage',
        'schema_json',
    ];

    protected $casts = [
        'schema_json' => 'array',
    ];

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }
}
