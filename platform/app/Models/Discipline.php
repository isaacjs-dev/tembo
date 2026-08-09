<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discipline extends Model
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

    public function knowledgeArea()
    {
        return $this->belongsTo(KnowledgeArea::class);
    }

    public function teachers()
    {
        return $this->belongsToMany(User::class, 'discipline_teacher', 'discipline_id', 'user_id')
            ->withPivot('organization_id', 'assigned_by', 'assigned_at')
            ->withTimestamps();
    }

    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_discipline', 'discipline_id', 'school_class_id')
            ->withPivot('organization_id', 'assigned_by')
            ->withTimestamps();
    }
}
