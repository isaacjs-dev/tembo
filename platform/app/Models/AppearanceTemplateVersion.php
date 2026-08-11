<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AppearanceTemplateVersion extends Model
{
    protected $fillable = [
        'appearance_template_id', 'version', 'schema_version', 'definition', 'assets',
        'content_hash', 'created_by', 'change_summary',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer', 'schema_version' => 'integer',
            'definition' => 'array', 'assets' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Versões de aparência são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Versões de aparência históricas não podem ser excluídas.'));
    }

    public function template()
    {
        return $this->belongsTo(AppearanceTemplate::class, 'appearance_template_id');
    }
}
