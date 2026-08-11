<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use OutOfBoundsException;

class OmrTemplate extends Model
{
    use HasFactory;

    protected $table = 'omr_templates';

    protected $fillable = [
        'name',
        'slug',
        'organization_id',
        'created_by',
        'owner_type',
        'owner_id',
        'visibility_scope',
        'is_default',
        'is_system',
        'width',
        'height',
        'paper_size',
        'orientation',
        'corner_points_json',
        'thresholds_json',
        'calibration_json',
        'qr_region_json',
        'layout_config',
        'header_config',
        'logo_path',
        'total_questions',
        'max_questions',
        'max_columns',
        'total_pages',
        'columns',
        'rows_per_column',
        'max_options',
        'current_version',
        'is_active',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'corner_points_json' => 'array',
            'thresholds_json' => 'array',
            'calibration_json' => 'array',
            'qr_region_json' => 'array',
            'layout_config' => 'array',
            'header_config' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_system' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $template): void {
            if ((bool) $template->getOriginal('is_system') || $template->is_system) {
                throw new LogicException('Templates OMR do sistema são imutáveis.');
            }
        });
        static::deleting(function (self $template): void {
            if ($template->is_system) {
                throw new LogicException('Templates OMR do sistema não podem ser excluídos.');
            }
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Owner polimórfico: Organization, User (professor) ou null (sistema). */
    public function owner()
    {
        return $this->morphTo();
    }

    public function versions()
    {
        return $this->hasMany(OmrTemplateVersion::class)->orderByDesc('version');
    }

    public function questions()
    {
        return $this->hasMany(OmrTemplateQuestion::class)->orderBy('question_number');
    }

    public function scans()
    {
        return $this->hasMany(OmrScan::class, 'omr_template_id');
    }

    /**
     * Geometria efetiva do template para uma versão (padrão: a atual).
     * Cai para `layout_config` da própria linha se a versão não existir.
     */
    public function layoutForVersion(?int $version = null): array
    {
        $version = $version ?? $this->current_version;
        $snapshot = $this->versions()->where('version', $version)->first();
        if ($snapshot && is_array($snapshot->layout_config)) {
            return $snapshot->layout_config;
        }

        if ((int) $version !== (int) $this->current_version || $this->versions()->exists()) {
            throw new OutOfBoundsException("A versão OMR {$version} não existe para o template {$this->id}.");
        }

        return is_array($this->layout_config) ? $this->layout_config : [];
    }

    public function currentLayout(): array
    {
        return $this->layoutForVersion($this->current_version);
    }

    /**
     * Visibilidade: templates do sistema + os do(s) org(s) ativos do usuário +
     * os de sua propriedade. Espelha o padrão de QuestionController::index.
     */
    public function scopeVisible($query, ?User $user = null)
    {
        $user = $user ?? auth()->user();
        $organizationId = $user?->organization_id ? (int) $user->organization_id : null;

        return $query->whereNull('archived_at')->where(function ($q) use ($organizationId, $user) {
            $q->where('visibility_scope', 'system')
                ->orWhere('is_system', true)
                ->orWhere(function ($systemDefault) {
                    $systemDefault->whereNull('organization_id')->where('is_default', true);
                });

            if ($organizationId) {
                $q->orWhere(function ($workspace) use ($organizationId, $user) {
                    $workspace->where('organization_id', $organizationId)
                        ->where(function ($visibility) use ($user) {
                            $visibility->where('visibility_scope', 'org_public')
                                ->orWhere('is_default', true);

                            if ($user) {
                                $visibility->orWhere('created_by', $user->id)
                                    ->orWhere(function ($owner) use ($user) {
                                        $owner->where('owner_type', User::class)
                                            ->where('owner_id', $user->id);
                                    });
                            }
                        });
                });
            }
        });
    }

    /**
     * Exporta o template para JSON consumível pela engine OMR client-side (TypeScript).
     */
    public function toEngineJson(): array
    {
        return [
            'template_id' => $this->id,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'paper_size' => $this->paper_size,
            'orientation' => $this->orientation,
            'corner_points' => $this->corner_points_json,
            'thresholds' => $this->thresholds_json,
            'calibration' => $this->calibration_json,
            'qr_region' => $this->qr_region_json,
            'total_questions' => $this->total_questions,
            'total_pages' => $this->total_pages,
            'columns' => $this->columns,
            'rows_per_column' => $this->rows_per_column,
            'max_options' => $this->max_options,
            'questions' => $this->questions->map(function ($q) {
                return [
                    'number' => $q->question_number,
                    'options' => $q->option_labels_json,
                    'rois' => $q->rois_json,
                    'weight' => $q->weight,
                ];
            })->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    public function snapshotForVersion(?int $version = null): array
    {
        $version ??= (int) $this->current_version;
        $record = $this->versions()->where('version', $version)->first();
        if (! $record && ((int) $version !== (int) $this->current_version || $this->versions()->exists())) {
            throw new OutOfBoundsException("A versão OMR {$version} não existe para o template {$this->id}.");
        }

        return [
            'id' => (int) $this->id,
            'version' => $version,
            'name' => $this->name,
            'schema_version' => (int) ($record?->schema_version ?? 1),
            'content_hash' => $record?->content_hash,
            'definition' => $record?->definition,
            'legacy_fallback' => $record === null,
            'version_source' => $record ? 'immutable_record' : 'live_legacy_current',
            'layout_config' => $record ? ($record->layout_config ?? []) : ($this->layout_config ?? []),
            'header_config' => $record ? ($record->header_config ?? []) : ($this->header_config ?? []),
            'logo_path' => $record ? $record->logo_path : $this->logo_path,
        ];
    }
}
