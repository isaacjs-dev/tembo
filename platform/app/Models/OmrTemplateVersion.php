<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OmrTemplateVersion extends Model
{
    protected $table = 'omr_template_versions';

    protected $fillable = [
        'omr_template_id',
        'version',
        'layout_config',
        'header_config',
        'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'layout_config' => 'array',
            'header_config' => 'array',
        ];
    }

    public function template()
    {
        return $this->belongsTo(OmrTemplate::class, 'omr_template_id');
    }
}
