<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AppearanceTemplate extends Model
{
    public const KINDS = ['assessment_layout', 'assessment_header', 'answer_sheet_header'];

    protected $fillable = [
        'organization_id', 'created_by', 'owner_type', 'owner_id', 'kind', 'name', 'slug',
        'visibility_scope', 'is_system', 'current_version', 'archived_at',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'current_version' => 'integer', 'archived_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $template): void {
            if ((bool) $template->getOriginal('is_system') || $template->is_system) {
                throw new LogicException('Templates de aparência do sistema são imutáveis.');
            }
        });
        static::deleting(function (self $template): void {
            if ($template->is_system) {
                throw new LogicException('Templates de aparência do sistema não podem ser excluídos.');
            }
        });
    }

    public function versions()
    {
        return $this->hasMany(AppearanceTemplateVersion::class)->orderByDesc('version');
    }

    public function currentVersion()
    {
        return $this->hasOne(AppearanceTemplateVersion::class)->ofMany('version', 'max');
    }

    public function owner()
    {
        return $this->morphTo();
    }

    public function scopeVisibleTo(Builder $query, User $user, ?Organization $organization = null): Builder
    {
        $organization ??= $user->organization;

        return $query->whereNull('archived_at')->where(function (Builder $visible) use ($user, $organization): void {
            $visible->where('is_system', true)->orWhere('visibility_scope', 'system');
            if ($organization && $user->canUseOrganizationContext((int) $organization->id)) {
                $visible->orWhere(function (Builder $workspace) use ($user, $organization): void {
                    $workspace->where('organization_id', $organization->id)
                        ->where(function (Builder $access) use ($user): void {
                            $access->where('visibility_scope', 'org_public')
                                ->orWhere(fn (Builder $owner) => $owner
                                    ->where('owner_type', User::class)->where('owner_id', $user->id));
                        });
                });
            }
        });
    }
}
