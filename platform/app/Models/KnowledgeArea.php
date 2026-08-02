<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
