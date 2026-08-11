<?php

namespace App\Services;

use App\Models\OmrTemplate;
use App\Models\OmrTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OmrTemplateVersionService
{
    public function append(OmrTemplate $template, User $actor): OmrTemplateVersion
    {
        return DB::transaction(function () use ($template, $actor): OmrTemplateVersion {
            $locked = OmrTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->is_system, 403, 'Templates OMR do sistema são imutáveis.');
            abort_if($locked->archived_at || ! $locked->is_active, 409, 'Template OMR arquivado não aceita novas versões.');
            $organizationId = (int) $locked->organization_id;
            abort_unless(
                $organizationId > 0
                && (int) $actor->organization_id === $organizationId
                && $actor->canUseOrganizationContext($organizationId),
                404,
            );
            $ownsTemplate = (int) $locked->created_by === (int) $actor->id
                || ($locked->owner_type === User::class && (int) $locked->owner_id === (int) $actor->id);
            $managesWorkspace = in_array(
                $actor->roleInOrganization($organizationId),
                ['admin', 'institution_admin', 'global_admin'],
                true,
            );
            abort_unless($ownsTemplate || $managesWorkspace, 403, 'Sem permissão para versionar este template OMR.');
            $locked->load('questions');
            $latest = (int) $locked->versions()->max('version');
            $version = $latest === 0 ? 1 : $latest + 1;
            $definition = $this->definition($locked);
            $encoded = json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $record = OmrTemplateVersion::query()->create([
                'omr_template_id' => $locked->id,
                'version' => $version,
                'schema_version' => 2,
                'layout_config' => $locked->layout_config ?? [],
                'header_config' => $locked->header_config ?? [],
                'logo_path' => $locked->logo_path,
                'definition' => $definition,
                'content_hash' => hash('sha256', $encoded),
                'created_by' => $actor->id,
            ]);
            $locked->update(['current_version' => $version]);

            return $record;
        }, 3);
    }

    /** @return array<string, mixed> */
    private function definition(OmrTemplate $template): array
    {
        return [
            'schema_version' => 2,
            'paper' => [
                'width' => (int) $template->width,
                'height' => (int) $template->height,
                'paper_size' => $template->paper_size,
                'orientation' => $template->orientation,
            ],
            'layout_config' => $template->layout_config ?? [],
            'header_config' => $template->header_config ?? [],
            'logo_path' => $template->logo_path,
            'corner_points' => $template->corner_points_json ?? [],
            'thresholds' => $template->thresholds_json ?? [],
            'calibration' => $template->calibration_json ?? [],
            'qr_region' => $template->qr_region_json ?? [],
            'questions' => $template->questions->map(fn ($question): array => [
                'question_number' => (int) $question->question_number,
                'option_labels' => $question->option_labels_json ?? [],
                'rois' => $question->rois_json ?? [],
                'weight' => (float) $question->weight,
            ])->values()->all(),
            'legacy_questions_source' => null,
        ];
    }
}
