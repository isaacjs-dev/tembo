<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InstitutionRole extends Model
{
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (InstitutionRole $role) {
            if (empty($role->slug)) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    /* ── Relationships ── */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function permissions()
    {
        return $this->hasMany(InstitutionRolePermission::class);
    }

    public function members()
    {
        return $this->hasMany(UserOrganization::class);
    }

    /* ── Helpers ── */

    /**
     * Retorna array de permission keys deste cargo.
     */
    public function getPermissionKeys(): array
    {
        return $this->permissions()->pluck('permission')->toArray();
    }

    /**
     * Verifica se o cargo tem uma permissão específica.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissions()->where('permission', $permission)->exists();
    }

    /**
     * Sync permissões (delete+insert).
     */
    public function syncPermissions(array $permissions): void
    {
        $this->permissions()->delete();
        foreach ($permissions as $perm) {
            $this->permissions()->create(['permission' => $perm]);
        }
    }

    /**
     * Lista de permissões configuráveis para perfis extras.
     * Nota: configs sensíveis NÃO estão aqui (password, billing, admin settings).
     */
    public static function availablePermissions(): array
    {
        return [
            'view_teachers' => 'Visualizar professores',
            'manage_teachers' => 'Gerenciar professores',
            'view_students' => 'Visualizar alunos',
            'manage_students' => 'Gerenciar alunos',
            'view_classes' => 'Visualizar turmas',
            'manage_classes' => 'Gerenciar turmas',
            'view_questions' => 'Visualizar questões',
            'manage_questions' => 'Gerenciar questões',
            'view_exams' => 'Visualizar avaliações',
            'manage_exams' => 'Gerenciar avaliações',
            'view_reports' => 'Visualizar relatórios',
            'view_invites' => 'Visualizar convites',
            'manage_invites' => 'Gerenciar convites',
            'view_omr' => 'Visualizar OMR',
            'manage_omr' => 'Gerenciar OMR',
            'view_pedagogical_content' => 'Visualizar conteudo pedagogico',
            'manage_pedagogical_content' => 'Gerenciar conteudo pedagogico',
            'review_revisions' => 'Revisar e publicar revisões pedagógicas',
        ];
    }
}
