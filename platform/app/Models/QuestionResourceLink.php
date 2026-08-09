<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class QuestionResourceLink extends Pivot
{
    protected $table = 'question_question_resource';

    public $incrementing = true;

    protected $casts = ['is_required' => 'boolean'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(QuestionResource::class, 'question_resource_id')->withTrashed();
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(QuestionResourceVersion::class, 'question_resource_version_id');
    }
}
