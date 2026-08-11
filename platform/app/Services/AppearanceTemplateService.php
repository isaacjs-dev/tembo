<?php

namespace App\Services;

use App\Models\AppearanceTemplate;
use App\Models\AppearanceTemplateVersion;
use App\Models\AuditLog;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\TemplateDefault;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppearanceTemplateService
{
    private readonly AppearanceDefinitionSchema $schema;

    public function __construct(?AppearanceDefinitionSchema $schema = null)
    {
        $this->schema = $schema ?? new AppearanceDefinitionSchema;
    }

    public function duplicate(
        AppearanceTemplate $source,
        User $actor,
        ?Organization $organization = null,
    ): AppearanceTemplate {
        $organization ??= $actor->organization;
        abort_unless(
            AppearanceTemplate::query()->visibleTo($actor, $organization)->whereKey($source->id)->exists(),
            404,
        );

        return DB::transaction(function () use ($source, $actor, $organization): AppearanceTemplate {
            $sourceVersion = AppearanceTemplateVersion::query()
                ->where('appearance_template_id', $source->id)->where('version', $source->current_version)->firstOrFail();
            $definition = $this->schema->normalize($source->kind, $sourceVersion->definition);
            $copy = AppearanceTemplate::query()->create([
                'organization_id' => $organization?->id,
                'created_by' => $actor->id,
                'owner_type' => User::class,
                'owner_id' => $actor->id,
                'kind' => $source->kind,
                'name' => 'Cópia de '.$source->name,
                'slug' => Str::slug($source->name).'-'.Str::lower(Str::random(8)),
                'visibility_scope' => 'private',
                'is_system' => false,
                'current_version' => 1,
            ]);
            $this->createVersionRecord(
                $copy, 1, $definition, $sourceVersion->assets ?? [], $actor,
                'Duplicado do template '.$source->id.' versão '.$sourceVersion->version.'.',
            );

            return $copy->fresh(['currentVersion']);
        }, 3);
    }

    public function create(
        User $actor,
        string $kind,
        string $name,
        array $definition,
        string $ownership = 'personal',
        array $assets = [],
    ): AppearanceTemplate {
        abort_unless(in_array($kind, ['assessment_layout', 'assessment_header'], true), 422);
        $organization = $actor->organization;
        abort_unless($organization && $actor->canUseOrganizationContext((int) $organization->id), 403);
        $institutional = $ownership === 'organization';
        if ($institutional) {
            abort_unless($actor->hasWorkspaceRole('admin', 'institution_admin', 'global_admin'), 403);
        }
        $definition = $this->schema->normalize($kind, $definition);

        return DB::transaction(function () use ($actor, $organization, $institutional, $kind, $name, $definition, $assets): AppearanceTemplate {
            $template = AppearanceTemplate::query()->create([
                'organization_id' => $organization->id,
                'created_by' => $actor->id,
                'owner_type' => $institutional ? Organization::class : User::class,
                'owner_id' => $institutional ? $organization->id : $actor->id,
                'kind' => $kind,
                'name' => trim($name),
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(10)),
                'visibility_scope' => $institutional ? 'org_public' : 'private',
                'is_system' => false,
                'current_version' => 1,
            ]);
            $this->createVersionRecord($template, 1, $definition, $assets, $actor, 'Versão inicial.');
            AuditLog::log('appearance_template_created', AppearanceTemplate::class, $template->id, [
                'kind' => $kind,
                'ownership' => $institutional ? 'organization' : 'personal',
                'version' => 1,
            ]);

            return $template->fresh(['currentVersion']);
        }, 3);
    }

    public function createVersion(
        AppearanceTemplate $template,
        User $actor,
        array $definition,
        array $assets = [],
        ?string $summary = null,
    ): AppearanceTemplateVersion {
        $this->authorizeMutation($template, $actor);

        return DB::transaction(function () use ($template, $actor, $definition, $assets, $summary): AppearanceTemplateVersion {
            $locked = AppearanceTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $this->authorizeMutation($locked, $actor);
            $definition = $this->schema->normalize($locked->kind, $definition);
            $next = max((int) $locked->current_version, (int) $locked->versions()->max('version')) + 1;
            $version = $this->createVersionRecord($locked, $next, $definition, $assets, $actor, $summary);
            $locked->update(['current_version' => $next]);
            TemplateDefault::query()
                ->where('template_type', AppearanceTemplate::class)
                ->where('template_id', $locked->id)
                ->update(['template_version' => $next, 'updated_at' => now()]);

            return $version;
        }, 3);
    }

    public function createVersionFromEditor(
        AppearanceTemplate $template,
        User $actor,
        int $baseVersion,
        array $definition,
        array $assets,
        ?string $summary = null,
    ): AppearanceTemplateVersion {
        $this->authorizeMutation($template, $actor);

        return DB::transaction(function () use ($template, $actor, $baseVersion, $definition, $assets, $summary): AppearanceTemplateVersion {
            $locked = AppearanceTemplate::query()->lockForUpdate()->findOrFail($template->id);
            $this->authorizeMutation($locked, $actor);
            abort_unless((int) $locked->current_version === $baseVersion, 409, 'Este template foi alterado em outra aba. Recarregue antes de salvar.');
            $definition = $this->schema->normalize($locked->kind, $definition);
            $next = $baseVersion + 1;
            $version = $this->createVersionRecord($locked, $next, $definition, $assets, $actor, $summary);
            $locked->update(['current_version' => $next]);
            TemplateDefault::query()->where('template_type', AppearanceTemplate::class)
                ->where('template_id', $locked->id)->update(['template_version' => $next, 'updated_at' => now()]);
            AuditLog::log('appearance_template_version_created', AppearanceTemplate::class, $locked->id, [
                'old_version' => $baseVersion,
                'new_version' => $next,
                'content_hash' => $version->content_hash,
            ]);

            return $version;
        }, 3);
    }

    public function rename(AppearanceTemplate $template, User $actor, string $name): void
    {
        $this->authorizeMutation($template, $actor);
        $name = trim($name);
        abort_unless($name !== '' && mb_strlen($name) <= 160, 422);
        $old = $template->name;
        $template->update(['name' => $name]);
        AuditLog::log('appearance_template_renamed', AppearanceTemplate::class, $template->id, [
            'old_name' => $old,
            'new_name' => $name,
        ]);
    }

    public function setDefault(
        AppearanceTemplate $template,
        User $actor,
        string $scope = 'user',
        ?Organization $organization = null,
    ): TemplateDefault {
        $organization ??= $actor->organization;
        abort_unless(in_array($scope, ['user', 'organization'], true), 422);
        abort_unless(
            AppearanceTemplate::query()->visibleTo($actor, $organization)->whereKey($template->id)->exists(),
            404,
        );
        if ($scope === 'organization') {
            abort_unless($organization && $actor->hasWorkspaceRole('admin', 'institution_admin', 'global_admin'), 403);
            abort_unless(
                $template->is_system
                || ((int) $template->organization_id === (int) $organization->id
                    && $template->visibility_scope === 'org_public'),
                422,
                'O padrão institucional precisa ser um template do sistema ou visível à instituição.',
            );
        }
        $scopeKey = $scope === 'user' ? 'user:'.$actor->id : 'organization:'.$organization->id;

        $default = TemplateDefault::query()->updateOrCreate(
            ['scope_key' => $scopeKey, 'kind' => $template->kind],
            [
                'organization_id' => $organization?->id,
                'user_id' => $scope === 'user' ? $actor->id : null,
                'template_type' => AppearanceTemplate::class,
                'template_id' => $template->id,
                'template_version' => $template->current_version,
                'set_by' => $actor->id,
            ],
        );
        AuditLog::log('appearance_template_default_changed', AppearanceTemplate::class, $template->id, [
            'scope' => $scope,
            'kind' => $template->kind,
            'version' => $template->current_version,
        ]);

        return $default;
    }

    public function archive(AppearanceTemplate $template, User $actor): void
    {
        $this->authorizeMutation($template, $actor);
        DB::transaction(function () use ($template): void {
            $locked = AppearanceTemplate::query()->lockForUpdate()->findOrFail($template->id);
            abort_if($locked->is_system, 403);
            $locked->update(['archived_at' => now()]);
            TemplateDefault::query()
                ->where('template_type', AppearanceTemplate::class)->where('template_id', $locked->id)->delete();
            AuditLog::log('appearance_template_archived', AppearanceTemplate::class, $locked->id, [
                'kind' => $locked->kind,
                'version' => $locked->current_version,
            ]);
        }, 3);
    }

    /** @return array{assessment_layout:Collection,assessment_header:Collection} */
    public function catalogFor(User $actor, Organization $organization, array $selectedVersionIds = []): array
    {
        $templates = AppearanceTemplate::query()
            ->visibleTo($actor, $organization)
            ->whereIn('kind', ['assessment_layout', 'assessment_header'])
            ->with('currentVersion')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        foreach (array_filter(array_map('intval', $selectedVersionIds)) as $versionId) {
            $version = AppearanceTemplateVersion::query()->with('template')->find($versionId);
            $template = $version?->template;
            if (! $template || ! in_array($template->kind, ['assessment_layout', 'assessment_header'], true)
                || ! $this->visibleInContext($template, $actor, $organization)) {
                continue;
            }
            if (! $template->archived_at
                && ! AppearanceTemplate::query()->visibleTo($actor, $organization)->whereKey($template->id)->exists()) {
                continue;
            }
            $currentVersionId = $templates->firstWhere('id', $template->id)?->currentVersion?->id;
            if ((int) $currentVersionId !== (int) $version->id) {
                $selectedTemplate = clone $template;
                $selectedTemplate->setRelation('currentVersion', $version);
                $selectedTemplate->setAttribute('selected_historical', true);
                $templates->push($selectedTemplate);
            }
        }

        return [
            'assessment_layout' => $templates->where('kind', 'assessment_layout')->values(),
            'assessment_header' => $templates->where('kind', 'assessment_header')->values(),
        ];
    }

    private function visibleInContext(AppearanceTemplate $template, User $actor, Organization $organization): bool
    {
        if ($template->is_system) {
            return true;
        }
        if ((int) $template->organization_id !== (int) $organization->id
            || ! $actor->canUseOrganizationContext((int) $organization->id)) {
            return false;
        }

        return $template->visibility_scope === 'org_public'
            || ($template->owner_type === User::class && (int) $template->owner_id === (int) $actor->id)
            || ($template->owner_type === Organization::class && (int) $template->owner_id === (int) $organization->id);
    }

    public function applySelection(
        Exam $exam,
        User $actor,
        ?int $layoutVersionId,
        ?int $headerVersionId,
    ): void {
        abort_unless((int) $exam->organization_id === (int) $actor->organization_id, 403);
        abort_unless((int) $exam->author_id === (int) $actor->id, 404);
        $layout = $this->selectableVersion($exam, $actor, 'assessment_layout', $layoutVersionId);
        $header = $this->selectableVersion($exam, $actor, 'assessment_header', $headerVersionId);
        $before = [
            'assessment_layout_version_id' => $exam->assessment_layout_version_id,
            'assessment_header_version_id' => $exam->assessment_header_version_id,
        ];
        $after = [
            'assessment_layout_version_id' => $layout?->id,
            'assessment_header_version_id' => $header?->id,
        ];
        if ($before !== $after) {
            $exam->forceFill($after)->save();
            AuditLog::log('exam_appearance_changed', Exam::class, $exam->id, [
                'old' => $before,
                'new' => $after,
            ]);
        }
    }

    private function selectableVersion(Exam $exam, User $actor, string $kind, ?int $versionId): ?AppearanceTemplateVersion
    {
        if (! $versionId) {
            return null;
        }
        $version = AppearanceTemplateVersion::query()->with('template')->find($versionId);
        abort_unless($version, 404);
        $template = $version->template;
        $selectedColumn = $kind === 'assessment_layout'
            ? 'assessment_layout_version_id'
            : 'assessment_header_version_id';
        if ($template->archived_at) {
            abort_unless(
                (int) $exam->{$selectedColumn} === (int) $version->id
                && $this->visibleInContext($template, $actor, $exam->organization),
                404,
            );
        } else {
            abort_unless(
                AppearanceTemplate::query()->visibleTo($actor, $exam->organization)
                    ->whereKey($version->appearance_template_id)->exists(),
                404,
            );
        }
        abort_unless($version->template->kind === $kind, 422, 'Template incompatível com o contexto visual.');

        return $version;
    }

    /** @return array<string, mixed> */
    public function snapshotForExam(Exam $exam, ?array $cardSnapshot = null, array $printOptions = []): array
    {
        $exam->loadMissing(['author', 'organization']);
        $mapping = [
            'assessment_layout' => 'assessment_layout_version_id',
            'assessment_header' => 'assessment_header_version_id',
            'answer_sheet_header' => 'answer_sheet_header_version_id',
        ];
        $snapshot = ['schema_version' => 2];

        foreach ($mapping as $kind => $column) {
            $version = $exam->{$column}
                ? AppearanceTemplateVersion::query()->with('template')->find($exam->{$column})
                : $this->resolveDefaultVersion($kind, $exam->author, $exam->organization);
            if ($version) {
                abort_unless($version->template->kind === $kind, 409, 'Template incompatível com o contexto visual.');
                abort_unless(
                    $version->template->is_system
                    || (int) $version->template->organization_id === (int) $exam->organization_id,
                    409,
                    'Template de outro contexto institucional não pode ser aplicado.',
                );
                if (! $version->template->is_system) {
                    $template = $version->template;
                    $authorId = (int) $exam->author_id;
                    $organizationId = (int) $exam->organization_id;
                    $visibleInContext = $template->visibility_scope === 'org_public'
                        || (int) $template->created_by === $authorId
                        || ($template->owner_type === User::class && (int) $template->owner_id === $authorId)
                        || ($template->owner_type === Organization::class && (int) $template->owner_id === $organizationId);
                    abort_unless($visibleInContext, 409, 'Template privado de outro proprietário não pode ser aplicado.');
                }
                $snapshot[$kind] = $this->versionSnapshot($version);
            }
        }

        if ($cardSnapshot) {
            $snapshot['answer_sheet_card'] = $cardSnapshot;
            // Aliases mantêm AnswerSheetGeneratorService, QR v3/v4/v5 e consumidores históricos intactos.
            $snapshot = array_merge($snapshot, Arr::only($cardSnapshot, [
                'id', 'version', 'name', 'layout_config', 'header_config', 'logo_path',
            ]));
        }
        $snapshot['print_preferences'] = Arr::only($printOptions, [
            'group_disciplines', 'shuffle_disciplines', 'show_discipline_name',
            'hide_question_term', 'show_question_value', 'show_option_brackets', 'question_separator',
        ]);

        return $snapshot;
    }

    private function resolveDefaultVersion(string $kind, User $user, Organization $organization): ?AppearanceTemplateVersion
    {
        foreach (['user:'.$user->id, 'organization:'.$organization->id, 'system'] as $scopeKey) {
            $default = TemplateDefault::query()->where('scope_key', $scopeKey)->where('kind', $kind)->first();
            if (! $default || $default->template_type !== AppearanceTemplate::class) {
                continue;
            }
            $template = AppearanceTemplate::query()
                ->visibleTo($user, $organization)
                ->find($default->template_id);
            if (! $template || $template->kind !== $kind) {
                continue;
            }
            $version = AppearanceTemplateVersion::query()
                ->where('appearance_template_id', $template->id)
                ->where('version', $default->template_version)->first();
            if ($version) {
                $version->setRelation('template', $template);

                return $version;
            }
        }

        return null;
    }

    private function createVersionRecord(
        AppearanceTemplate $template,
        int $version,
        array $definition,
        array $assets,
        User $actor,
        ?string $summary,
    ): AppearanceTemplateVersion {
        $assets = $this->schema->normalizeAssets($assets);
        foreach ($definition['elements'] ?? [] as $element) {
            if (($element['type'] ?? null) === 'image'
                && ! array_key_exists((string) ($element['asset_key'] ?? ''), $assets)) {
                throw ValidationException::withMessages([
                    'asset' => 'Envie a imagem referenciada antes de salvar a versão.',
                ]);
            }
        }
        $payload = ['definition' => $this->canonical($definition), 'assets' => $this->canonical($assets)];
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return AppearanceTemplateVersion::query()->create([
            'appearance_template_id' => $template->id,
            'version' => $version,
            'schema_version' => ($definition['mode'] ?? null) === 'canvas' ? 2 : 1,
            'definition' => $payload['definition'],
            'assets' => $payload['assets'],
            'content_hash' => hash('sha256', $encoded),
            'created_by' => $actor->id,
            'change_summary' => $summary,
        ]);
    }

    public function authorizeMutation(AppearanceTemplate $template, User $actor): void
    {
        abort_if(! $template->is_system
            && (int) $template->organization_id !== (int) $actor->organization_id, 404);
        abort_if($template->is_system, 403, 'Templates do sistema são imutáveis.');
        abort_if($template->archived_at, 409, 'Templates arquivados são somente leitura.');
        abort_unless((int) $template->organization_id === (int) $actor->organization_id, 404);
        $owns = $template->owner_type === User::class && (int) $template->owner_id === (int) $actor->id;
        $manages = $template->owner_type === Organization::class
            && $actor->hasWorkspaceRole('admin', 'institution_admin', 'global_admin');
        abort_unless($owns || $manages, 403);
    }

    public function canMutate(AppearanceTemplate $template, User $actor): bool
    {
        if ($template->is_system || $template->archived_at
            || (int) $template->organization_id !== (int) $actor->organization_id) {
            return false;
        }

        return ($template->owner_type === User::class && (int) $template->owner_id === (int) $actor->id)
            || ($template->owner_type === Organization::class
                && $actor->hasWorkspaceRole('admin', 'institution_admin', 'global_admin'));
    }

    private function canonical(array $value): array
    {
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }
        unset($item);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /** @return array<string, mixed> */
    private function versionSnapshot(AppearanceTemplateVersion $version): array
    {
        return [
            'template_id' => (int) $version->appearance_template_id,
            'version_id' => (int) $version->id,
            'version' => (int) $version->version,
            'schema_version' => (int) $version->schema_version,
            'kind' => $version->template->kind,
            'name' => $version->template->name,
            'content_hash' => $version->content_hash,
            'definition' => $version->definition,
            'assets' => $version->assets ?? [],
        ];
    }
}
