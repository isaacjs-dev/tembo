<?php

namespace App\Models;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SchoolClass extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    protected $fillable = [
        'organization_id',
        'owner_type',
        'owner_id',
        'name',
        'year',
    ];

    /* ── Relationships ── */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'class_student', 'school_class_id', 'user_id');
    }

    /**
     * Professores atribuídos a esta turma.
     */
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'class_teacher', 'school_class_id', 'user_id')
            ->withPivot('assigned_at');
    }

    public function disciplines()
    {
        return $this->belongsToMany(Discipline::class, 'class_discipline', 'school_class_id', 'discipline_id')
            ->withPivot('organization_id', 'assigned_by')
            ->withTimestamps();
    }

    /**
     * Proprietário morph (Organization ou User).
     */
    public function owner()
    {
        return $this->morphTo('owner', 'owner_type', 'owner_id');
    }

    /**
     * Histórico de transferências de propriedade.
     */
    public function ownershipLogs()
    {
        return $this->hasMany(ClassOwnershipLog::class)->orderByDesc('transferred_at');
    }

    /* ── Helpers ── */

    /**
     * Verifica se um usuário é proprietário desta turma.
     */
    public function isOwnedBy(User $user): bool
    {
        if ($this->owner_type === 'user') {
            return $this->owner_id === $user->id;
        }

        // Se pertence a uma org, o admin da org é "dono"
        if ($this->owner_type === 'organization') {
            return $user->hasWorkspaceRole('admin', 'institution_admin')
                && $user->organization_id == $this->owner_id;
        }

        return false;
    }

    /**
     * Verifica se um professor está atribuído a esta turma.
     */
    public function hasTeacher(User $teacher): bool
    {
        return $this->teachers()->where('users.id', $teacher->id)->exists();
    }
}
