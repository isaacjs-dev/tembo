<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionResourceShare extends Model
{
    protected $fillable = ['question_resource_id', 'shared_with_user_id'];

    public function resource(): BelongsTo
    {
        return $this->belongsTo(QuestionResource::class, 'question_resource_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_with_user_id');
    }
}
