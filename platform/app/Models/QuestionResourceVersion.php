<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionResourceVersion extends Model
{
    protected $fillable = [
        'question_resource_id',
        'version_number',
        'content',
        'content_hash',
        'storage_disk',
        'storage_path',
        'mime_type',
        'size_bytes',
        'sha256',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Versões de recursos de questão são imutáveis.'));
        static::deleting(fn () => throw new \LogicException('Versões de recursos de questão não podem ser removidas individualmente.'));
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(QuestionResource::class, 'question_resource_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
