<?php

use App\Models\AppearanceTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appearance_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('owner');
            $table->string('kind', 40);
            $table->string('name', 160);
            $table->string('slug', 190)->unique();
            $table->string('visibility_scope', 30)->default('private');
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'visibility_scope', 'archived_at'], 'appearance_templates_catalog_index');
            $table->index(['organization_id', 'kind', 'archived_at'], 'appearance_templates_workspace_index');
        });

        Schema::create('appearance_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appearance_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('schema_version')->default(1);
            $table->json('definition');
            $table->json('assets')->nullable();
            $table->char('content_hash', 64);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_summary', 500)->nullable();
            $table->timestamps();

            $table->unique(['appearance_template_id', 'version'], 'appearance_template_version_unique');
            $table->index(['content_hash'], 'appearance_template_content_hash_index');
        });

        Schema::create('template_defaults', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('scope_key', 100);
            $table->string('kind', 40);
            $table->string('template_type');
            $table->unsignedBigInteger('template_id');
            $table->unsignedInteger('template_version');
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scope_key', 'kind'], 'template_defaults_scope_kind_unique');
            $table->index(['template_type', 'template_id'], 'template_defaults_template_index');
        });

        Schema::table('exams', function (Blueprint $table): void {
            $table->foreignId('assessment_layout_version_id')->nullable()->constrained('appearance_template_versions')->nullOnDelete();
            $table->foreignId('assessment_header_version_id')->nullable()->constrained('appearance_template_versions')->nullOnDelete();
            $table->foreignId('answer_sheet_header_version_id')->nullable()->constrained('appearance_template_versions')->nullOnDelete();
        });

        Schema::table('omr_templates', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('is_active')->index();
        });

        Schema::table('omr_template_versions', function (Blueprint $table): void {
            $table->unsignedInteger('schema_version')->default(1)->after('version');
            $table->json('definition')->nullable()->after('logo_path');
            $table->char('content_hash', 64)->nullable()->after('definition')->index();
            $table->foreignId('created_by')->nullable()->after('content_hash')->constrained('users')->nullOnDelete();
        });

        $this->seedSystemTemplates();
        $this->backfillCardVersionHashes();
    }

    public function down(): void
    {
        if (DB::table('appearance_templates')->where('is_system', false)->exists()
            || DB::table('exams')->whereNotNull('assessment_layout_version_id')
                ->orWhereNotNull('assessment_header_version_id')->orWhereNotNull('answer_sheet_header_version_id')->exists()) {
            throw new RuntimeException(
                'Rollback recusado: existem templates personalizados ou snapshots históricos de aparência.',
            );
        }

        Schema::table('omr_template_versions', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['schema_version', 'definition', 'content_hash', 'created_by']);
        });
        Schema::table('omr_templates', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });
        Schema::table('exams', function (Blueprint $table): void {
            $table->dropForeign(['assessment_layout_version_id']);
            $table->dropForeign(['assessment_header_version_id']);
            $table->dropForeign(['answer_sheet_header_version_id']);
            $table->dropColumn([
                'assessment_layout_version_id', 'assessment_header_version_id', 'answer_sheet_header_version_id',
            ]);
        });
        Schema::dropIfExists('template_defaults');
        Schema::dropIfExists('appearance_template_versions');
        Schema::dropIfExists('appearance_templates');
    }

    private function seedSystemTemplates(): void
    {
        $definitions = [
            'assessment_layout' => [
                'name' => 'Clássico A4',
                'definition' => ['page' => ['size' => 'A4', 'orientation' => 'portrait', 'margins_mm' => [15, 15, 15, 15]], 'questions' => ['columns' => 1, 'separator' => 'line', 'avoid_break_inside' => true]],
            ],
            'assessment_header' => [
                'name' => 'Acadêmico essencial',
                'definition' => ['height_mm' => 34, 'elements' => [['type' => 'text', 'token' => 'assessment.title'], ['type' => 'text', 'token' => 'institution.name'], ['type' => 'field', 'token' => 'student.name']]],
            ],
            'answer_sheet_header' => [
                'name' => 'Cartão essencial',
                'definition' => ['height_mm' => 40, 'qr_slot' => ['position' => 'top_right', 'width_mm' => 30, 'quiet_zone_mm' => 4], 'fields' => ['institution.name', 'student.name', 'class.name', 'assessment.title']],
            ],
        ];
        $now = now();

        foreach ($definitions as $kind => $data) {
            $templateId = DB::table('appearance_templates')->insertGetId([
                'kind' => $kind,
                'name' => $data['name'],
                'slug' => 'system-'.$kind.'-essential',
                'visibility_scope' => 'system',
                'is_system' => true,
                'current_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $payload = ['definition' => $data['definition'], 'assets' => []];
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            DB::table('appearance_template_versions')->insert([
                'appearance_template_id' => $templateId,
                'version' => 1,
                'schema_version' => 1,
                'definition' => json_encode($data['definition'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'assets' => json_encode([], JSON_THROW_ON_ERROR),
                'content_hash' => hash('sha256', $encoded),
                'change_summary' => 'Baseline de compatibilidade do sistema.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('template_defaults')->insert([
                'scope_key' => 'system',
                'kind' => $kind,
                'template_type' => AppearanceTemplate::class,
                'template_id' => $templateId,
                'template_version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function backfillCardVersionHashes(): void
    {
        DB::table('omr_template_versions')->orderBy('id')->chunkById(200, function ($versions): void {
            foreach ($versions as $version) {
                $template = DB::table('omr_templates')->where('id', $version->omr_template_id)->first();
                $isCurrent = (int) $template?->current_version === (int) $version->version;
                $questions = $isCurrent
                    ? DB::table('omr_template_questions')->where('omr_template_id', $version->omr_template_id)
                        ->orderBy('question_number')->get()->map(fn ($question): array => [
                            'question_number' => (int) $question->question_number,
                            'option_labels' => json_decode($question->option_labels_json ?? '[]', true),
                            'rois' => json_decode($question->rois_json ?? '[]', true),
                            'weight' => (float) $question->weight,
                        ])->all()
                    : null;
                $payload = [
                    'schema_version' => 2,
                    'paper' => [
                        'width' => (int) ($template?->width ?? 2480),
                        'height' => (int) ($template?->height ?? 3508),
                        'paper_size' => $template?->paper_size ?? 'A4',
                        'orientation' => $template?->orientation ?? 'portrait',
                    ],
                    'layout_config' => json_decode($version->layout_config ?? '[]', true),
                    'header_config' => json_decode($version->header_config ?? '[]', true),
                    'logo_path' => $version->logo_path,
                    'corner_points' => json_decode($template?->corner_points_json ?? '[]', true),
                    'thresholds' => json_decode($template?->thresholds_json ?? '[]', true),
                    'calibration' => json_decode($template?->calibration_json ?? '[]', true),
                    'qr_region' => json_decode($template?->qr_region_json ?? '[]', true),
                    'questions' => $questions,
                    'legacy_questions_source' => $isCurrent
                        ? 'current_template_at_v2_backfill'
                        : 'not_versioned_before_schema_v2',
                ];
                $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                DB::table('omr_template_versions')->where('id', $version->id)->update([
                    'schema_version' => 2,
                    'definition' => $encoded,
                    'content_hash' => hash('sha256', $encoded),
                    'created_by' => $template?->created_by,
                ]);
            }
        });
    }
};
