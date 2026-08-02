<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        ];
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

        if ($user && $user->type === 'global_admin') {
            return $query;
        }

        $orgIds = [];
        if ($user) {
            $orgIds = $user->activeOrganizations()->pluck('organizations.id')->toArray();
            if ($user->organization_id && ! in_array($user->organization_id, $orgIds)) {
                $orgIds[] = $user->organization_id;
            }
        }

        return $query->where(function ($q) use ($orgIds, $user) {
            $q->where('visibility_scope', 'system')
                ->orWhere('is_system', true)
                ->orWhere('is_default', true);
            if (! empty($orgIds)) {
                $q->orWhereIn('organization_id', $orgIds);
            }
            if ($user) {
                $q->orWhere(function ($q2) use ($user) {
                    $q2->where('owner_type', User::class)->where('owner_id', $user->id);
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
}
