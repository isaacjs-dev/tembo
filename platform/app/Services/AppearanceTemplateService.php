<?php

namespace App\Services;

use App\Models\AppearanceTemplate;
use App\Models\AppearanceTemplateVersion;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\TemplateDefault;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        return TemplateDefault::query()->updateOrCreate(
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
        }, 3);
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
        $payload = ['definition' => $this->canonical($definition), 'assets' => $this->canonical($assets)];
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return AppearanceTemplateVersion::query()->create([
            'appearance_template_id' => $template->id,
            'version' => $version,
            'schema_version' => 1,
            'definition' => $payload['definition'],
            'assets' => $payload['assets'],
            'content_hash' => hash('sha256', $encoded),
            'created_by' => $actor->id,
            'change_summary' => $summary,
        ]);
    }

    private function authorizeMutation(AppearanceTemplate $template, User $actor): void
    {
        abort_if($template->is_system, 403, 'Templates do sistema são imutáveis.');
        abort_unless((int) $template->organization_id === (int) $actor->organization_id, 404);
        $owns = $template->owner_type === User::class && (int) $template->owner_id === (int) $actor->id;
        $manages = $template->owner_type === Organization::class
            && $actor->hasWorkspaceRole('admin', 'institution_admin', 'global_admin');
        abort_unless($owns || $manages, 403);
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
