<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OmrTemplateQuestion extends Model
{
    protected $fillable = [
        'omr_template_id', 'question_number', 'option_labels_json', 'rois_json', 'weight',
    ];

    protected function casts(): array
    {
        return [
            'option_labels_json' => 'array',
            'rois_json' => 'array',
            'weight' => 'float',
        ];
    }

    public function template()
    {
        return $this->belongsTo(OmrTemplate::class, 'omr_template_id');
    }
}
