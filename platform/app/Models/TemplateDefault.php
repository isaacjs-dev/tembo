<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateDefault extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'scope_key', 'kind', 'template_type',
        'template_id', 'template_version', 'set_by',
    ];

    protected function casts(): array
    {
        return ['template_id' => 'integer', 'template_version' => 'integer'];
    }

    public function template()
    {
        return $this->morphTo();
    }
}
