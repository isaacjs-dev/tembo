<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class OmrTemplateVersion extends Model
{
    protected $table = 'omr_template_versions';

    protected $fillable = [
        'omr_template_id',
        'version',
        'schema_version',
        'layout_config',
        'header_config',
        'logo_path',
        'definition',
        'content_hash',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'layout_config' => 'array',
            'header_config' => 'array',
            'definition' => 'array',
            'schema_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Versões OMR são imutáveis.'));
        static::deleting(fn () => throw new LogicException('Versões OMR históricas não podem ser excluídas.'));
    }

    public function template()
    {
        return $this->belongsTo(OmrTemplate::class, 'omr_template_id');
    }
}
