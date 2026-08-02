<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnswerSheetType extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'slug',
        'name',
        'description',
        'is_system',
        'is_default',
        'layout_config',
        'grading_config',
        'version',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'layout_config' => 'array',
            'grading_config' => 'array',
            'is_system' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /* ── Relationships ── */

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /* ── Scopes ── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where(function ($q) use ($organizationId) {
            $q->whereNull('organization_id')
                ->orWhere('organization_id', $organizationId);
        });
    }

    /* ── Helpers ── */

    /**
     * Exporta layout para a engine OMR do Duoscanner.
     */
    public function toEngineLayout(): array
    {
        $layout = $this->layout_config;

        return [
            'slug' => $this->slug,
            'version' => $this->version,
            'paper_size' => $layout['paper_size'] ?? 'A4',
            'orientation' => $layout['orientation'] ?? 'portrait',
            'columns' => $layout['columns'] ?? 4,
            'rows_per_column' => $layout['rows_per_column'] ?? 15,
            'max_options' => $layout['max_options'] ?? 5,
            'bubble_diameter_mm' => $layout['bubble_diameter_mm'] ?? 4.2,
            'fiducial_size_mm' => $layout['fiducial_size_mm'] ?? 4.76,
            'area_width_mm' => $layout['area_width_mm'] ?? 184.5,
            'area_height_mm' => $layout['area_height_mm'] ?? 153.4,
            'col_bubble_start_mm' => $layout['col_bubble_start_mm'] ?? [],
            'col_bubble_end_mm' => $layout['col_bubble_end_mm'] ?? [],
            'grid_top_offset_mm' => $layout['grid_top_offset_mm'] ?? 15.8,
            'discipline_header_height_mm' => $layout['discipline_header_height_mm'] ?? 6.8,
            'qr_position' => $layout['qr_position'] ?? 'top_left',
            'max_questions' => $layout['max_questions'] ?? 60,
        ];
    }

    /**
     * Retorna a configuração de correção para o GradingService.
     */
    public function getGradingConfig(): array
    {
        return array_merge([
            'blank_handling' => 'zero_points',
            'multiple_marks_handling' => 'zero_points',
            'partial_credit' => false,
            'confidence_threshold_auto_accept' => 0.80,
            'confidence_threshold_review' => 0.60,
            'confidence_threshold_rescan' => 0.45,
        ], $this->grading_config ?? []);
    }
}
