<?php

namespace Tests\Feature;

use App\Models\AppearanceTemplate;
use App\Models\Exam;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use App\Services\AppearanceDefinitionSchema;
use App\Services\AppearanceTemplateService;
use App\Services\CanonicalPrintDocumentService;
use App\Services\ExamPrintService;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CanonicalPrintRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_definitions_are_valid_and_unknown_css_urls_and_tokens_are_rejected(): void
    {
        $schema = app(AppearanceDefinitionSchema::class);
        foreach (AppearanceTemplate::query()->where('is_system', true)->with('currentVersion')->get() as $template) {
            $this->assertIsArray($schema->normalize($template->kind, $template->currentVersion->definition));
        }

        foreach ([
            ['height_mm' => 30, 'elements' => [['type' => 'text', 'token' => 'student.password']]],
            ['height_mm' => 30, 'elements' => [], 'css' => 'body{display:none}'],
            ['height_mm' => 30, 'elements' => [['type' => 'text', 'text' => '{{student.name}}']], 'url' => 'https://evil.test'],
        ] as $definition) {
            try {
                $schema->normalize('assessment_header', $definition);
                $this->fail('Definição insegura foi aceita.');
            } catch (DomainException) {
                $this->assertTrue(true);
            }
        }

        foreach ([
            ['logo' => ['storage_disk' => 'public', 'storage_path' => '../secret.png', 'mime_type' => 'image/png', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64)]],
            ['logo' => ['storage_disk' => 'public', 'storage_path' => 'logos/logo.svg', 'mime_type' => 'image/svg+xml', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64)]],
            ['logo' => ['storage_disk' => 'public', 'storage_path' => 'https://evil.test/logo.png', 'mime_type' => 'image/png', 'size_bytes' => 10, 'sha256' => str_repeat('a', 64)]],
            ['logo' => ['storage_disk' => 'public', 'storage_path' => 'logos/logo.png', 'mime_type' => 'image/png', 'size_bytes' => 10, 'sha256' => 'invalid']],
        ] as $assets) {
            try {
                $schema->normalizeAssets($assets);
                $this->fail('Asset inseguro foi aceito.');
            } catch (DomainException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_preview_and_copy_share_the_same_canonical_snapshot_and_escaped_view(): void
    {
        [$exam, $question, $teacher] = $this->context('<script>alert(1)</script> Avaliação');
        $question->update(['content' => [
            'statement' => '<img src=x onerror=alert(2)>Enunciado seguro',
            'options' => ['<b>Opção A</b>', 'Opção B'],
            'correct_option' => 0,
        ]]);

        $documents = app(CanonicalPrintDocumentService::class);
        $preview = $documents->preview($exam->fresh());
        $copy = app(ExamPrintService::class)->generateCopies($exam->fresh(), 1, ['output_type' => 'exam'])->first();
        $historical = $documents->copy($exam->fresh(), $copy);

        $this->assertSame($preview['questions'], $historical['questions']);
        $this->assertSame($preview['layout'], $historical['layout']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $preview['document_hash']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $historical['document_hash']);
        $this->assertSame('live_draft_preview', $preview['source']);
        $this->assertSame('immutable_exam_copy', $historical['source']);
        $this->assertStringContainsString('____', data_get($preview, 'header.elements.2.value'));

        $html = view('exams.print.canonical-preview', ['document' => $historical])->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('<b>Opção A</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('Enunciado seguro', $html);
        $this->assertStringContainsString('Opção A', $html);

        $this->actingAs($teacher)
            ->withSession(['workspace_id' => $exam->organization_id])
            ->get(route('exams.previewPrint', $exam))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_historical_identity_context_and_hash_do_not_change_with_live_names(): void
    {
        [$exam, , $teacher] = $this->context('Identidade original');
        $copy = app(ExamPrintService::class)->generateCopies($exam, 1, ['output_type' => 'exam'])->first();
        $documents = app(CanonicalPrintDocumentService::class);
        $before = $documents->copy($exam, $copy);

        $exam->update(['title' => 'Título vivo alterado']);
        $teacher->update(['name' => 'Professor alterado']);
        $exam->organization->update(['name' => 'Instituição alterada']);
        $after = $documents->copy($exam->fresh(), $copy->fresh());

        $this->assertSame($before['document_hash'], $after['document_hash']);
        $this->assertSame($before['context'], $after['context']);
        $this->assertSame('Identidade original', data_get($after, 'context.assessment.title'));
    }

    public function test_historical_document_does_not_read_live_question_or_template_state(): void
    {
        [$exam, $question] = $this->context('Avaliação histórica');
        $documents = app(CanonicalPrintDocumentService::class);
        $copy = app(ExamPrintService::class)->generateCopies($exam, 1, ['output_type' => 'exam'])->first();
        $before = $documents->copy($exam, $copy);
        $beforeHtml = view('exams.print.canonical-preview', ['document' => $before])->render();

        $question->update(['content' => [
            'statement' => 'CONTEÚDO VIVO ALTERADO',
            'options' => ['Nova A', 'Nova B'],
            'correct_option' => 1,
        ]]);
        $exam->questions()->updateExistingPivot($question->id, ['points' => 99]);
        $exam->questions()->detach($question->id);

        $after = $documents->copy($exam->fresh(), $copy->fresh());
        $afterHtml = view('exams.print.canonical-preview', ['document' => $after])->render();
        $this->assertSame($before['source_hash'], $after['source_hash']);
        $this->assertSame($before['questions'], $after['questions']);
        $this->assertSame($beforeHtml, $afterHtml);
        $this->assertStringNotContainsString('CONTEÚDO VIVO ALTERADO', $afterHtml);
    }

    public function test_invalid_question_and_option_maps_fail_closed(): void
    {
        [$exam] = $this->context('Avaliação inválida');
        $copy = app(ExamPrintService::class)->generateCopies($exam, 1)->first();
        $copy->forceFill(['questions_map' => [999999]])->save();

        $this->expectException(RuntimeException::class);
        app(CanonicalPrintDocumentService::class)->copy($exam, $copy->fresh());
    }

    public function test_unknown_question_type_fails_closed(): void
    {
        [$exam] = $this->context('Avaliação com tipo inválido');
        $copy = app(ExamPrintService::class)->generateCopies($exam, 1)->first();
        $snapshot = $copy->question_snapshot;
        $snapshot[0]['type'] = 'executable_widget';
        $copy->forceFill(['question_snapshot' => $snapshot])->save();

        $this->expectException(RuntimeException::class);
        app(CanonicalPrintDocumentService::class)->copy($exam, $copy->fresh());
    }

    public function test_print_preferences_are_reflected_in_the_canonical_view(): void
    {
        [$exam] = $this->context('Preferências canônicas');
        $document = app(CanonicalPrintDocumentService::class)->preview($exam, [
            'hide_question_term' => true,
            'show_question_value' => false,
            'show_option_brackets' => true,
            'question_separator' => '-',
        ]);
        $html = view('exams.print.canonical-preview', compact('document'))->render();

        $this->assertStringContainsString('1- Enunciado original', $html);
        $this->assertStringNotContainsString('Questão 1', $html);
        $this->assertStringNotContainsString('(Valor:', $html);
        $this->assertStringContainsString('canonical-option-mark', $html);
    }

    public function test_combined_pdf_keeps_the_single_portrait_omr_page_rule(): void
    {
        [$exam] = $this->context('Lote combinado');
        $copies = app(ExamPrintService::class)->generateCopies($exam, 1, ['output_type' => 'both']);
        $document = app(CanonicalPrintDocumentService::class)->copy($exam, $copies->first());
        $printDocuments = collect([$copies->first()->id => $document]);
        $cardPagesByCopy = [];
        $calibration = [];
        $options = [];
        $outputType = 'both';

        $html = view('exams.pdf_advanced', compact(
            'exam', 'copies', 'printDocuments', 'cardPagesByCopy', 'calibration', 'options', 'outputType'
        ))->render();

        $this->assertSame(1, substr_count($html, '@page'));
        $this->assertStringContainsString('@page { size: A4 portrait; margin: 10mm; }', $html);
        $this->assertStringNotContainsString('size: A4 landscape', $html);
    }

    public function test_long_single_question_allows_safe_page_breaks(): void
    {
        [$exam, $question] = $this->context('Avaliação com questão extensa');
        $question->update(['content' => [
            'statement' => str_repeat('Conteúdo extenso para paginação segura. ', 100),
            'options' => ['Alternativa A', 'Alternativa B'],
            'correct_option' => 0,
        ]]);

        $document = app(CanonicalPrintDocumentService::class)->preview($exam->fresh());

        $this->assertFalse($document['questions'][0]['avoid_break']);
        $this->assertDoesNotMatchRegularExpression(
            '/<article class="canonical-question\s+avoid-break/',
            view('exams.print.canonical-preview', compact('document'))->render(),
        );
    }

    public function test_two_column_layout_falls_back_to_one_for_an_oversized_question(): void
    {
        [$exam, $question, $teacher] = $this->context('Layout seguro');
        $question->update(['content' => [
            'statement' => str_repeat('Questão longa em layout de colunas. ', 100),
            'options' => ['A', 'B'],
            'correct_option' => 0,
        ]]);
        $systemLayout = AppearanceTemplate::query()->where('kind', 'assessment_layout')->firstOrFail();
        $layout = app(AppearanceTemplateService::class)
            ->duplicate($systemLayout, $teacher, $exam->organization);
        $version = app(AppearanceTemplateService::class)->createVersion($layout, $teacher, [
            'page' => ['size' => 'A4', 'orientation' => 'portrait', 'margins_mm' => [15, 15, 15, 15]],
            'questions' => ['columns' => 2, 'separator' => 'line', 'avoid_break_inside' => true],
        ]);
        $exam->update(['assessment_layout_version_id' => $version->id]);

        $document = app(CanonicalPrintDocumentService::class)->preview($exam->fresh());

        $this->assertSame(1, $document['layout']['columns']);
        $this->assertSame('oversized_question', $document['layout']['columns_fallback_reason']);
    }

    public function test_preview_is_author_and_tenant_scoped(): void
    {
        [$exam] = $this->context('Avaliação privada');
        $otherOrganization = Organization::query()->create(['name' => 'Outra escola', 'active' => true]);
        $other = User::factory()->create([
            'organization_id' => $otherOrganization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $other->assignRole('teacher');
        $other->organizations()->attach($otherOrganization->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);

        $this->actingAs($other)
            ->withSession(['workspace_id' => $otherOrganization->id])
            ->get(route('exams.previewPrint', $exam))
            ->assertNotFound();
    }

    public function test_long_canonical_document_generates_a_multi_page_a4_pdf_without_empty_tail(): void
    {
        [$exam, , $teacher] = $this->context('Avaliação longa');
        $exam->questions()->detach();
        foreach (range(1, 24) as $index) {
            $question = Question::query()->create([
                'organization_id' => $exam->organization_id,
                'owner_id' => $teacher->id,
                'type' => 'multiple_choice',
                'content' => [
                    'statement' => "Questão longa {$index} ".str_repeat('conteúdo educacional ', 18),
                    'options' => ['Alternativa A', 'Alternativa B', 'Alternativa C', 'Alternativa D'],
                    'correct_option' => 0,
                ],
                'visibility_scope' => 'private',
            ]);
            $exam->questions()->attach($question, ['points' => 1, 'order' => $index]);
        }

        $copy = app(ExamPrintService::class)->generateCopies($exam->fresh(), 1, ['output_type' => 'exam'])->first();
        $document = app(CanonicalPrintDocumentService::class)->copy($exam->fresh(), $copy);
        $pdf = Pdf::loadView('exams.print.canonical-pdf', compact('document'));
        $bytes = $pdf->output();
        $pages = $pdf->getDomPDF()->getCanvas()->get_page_count();

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertGreaterThan(1, $pages);
        $this->assertLessThanOrEqual(24, $pages);
    }

    /** @return array{Exam, Question, User} */
    private function context(string $title): array
    {
        $organization = Organization::query()->create(['name' => 'Escola Canônica', 'active' => true]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacher->assignRole('teacher');
        $teacher->organizations()->attach($organization->id, [
            'role_in_org' => 'teacher', 'status' => 'active', 'joined_at' => now(),
        ]);
        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => $title,
            'status' => 'draft',
        ]);
        $question = Question::query()->create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Enunciado original',
                'options' => ['Alternativa A', 'Alternativa B'],
                'correct_option' => 0,
            ],
            'visibility_scope' => 'private',
        ]);
        $exam->questions()->attach($question, ['points' => 2, 'order' => 1]);

        return [$exam, $question, $teacher];
    }
}
