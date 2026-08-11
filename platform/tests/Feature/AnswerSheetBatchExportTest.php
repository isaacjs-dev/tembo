<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use App\Models\Organization;
use App\Models\User;
use App\Services\AnswerSheetGeneratorService;
use App\Services\ConfigPrecedenceResolver;
use App\Services\ExamPrintService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AnswerSheetBatchExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_sends_every_requested_copy_to_the_pdf_generator(): void
    {
        Role::findOrCreate('teacher', 'web');
        $organization = Organization::create([
            'name' => 'Escola Impressão',
            'active' => true,
        ]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $teacher->assignRole('teacher');
        $exam = Exam::create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Prova em lote',
            'status' => 'published',
            'settings' => ['application_mode' => 'paper'],
        ]);
        $template = OmrTemplate::create([
            'name' => 'Cartão padrão',
            'slug' => 'cartao-padrao-lote',
            'organization_id' => $organization->id,
            'created_by' => $teacher->id,
            'corner_points_json' => [],
            'thresholds_json' => [],
            'layout_config' => [],
            'is_default' => true,
            'is_active' => true,
            'current_version' => 1,
        ]);
        $copies = new EloquentCollection([
            new ExamCopy(['copy_number' => 1]),
            new ExamCopy(['copy_number' => 2]),
            new ExamCopy(['copy_number' => 3]),
        ]);

        $printService = Mockery::mock(ExamPrintService::class);
        $printService->shouldReceive('generateCopies')
            ->once()
            ->withArgs(fn (Exam $receivedExam, int $quantity): bool => $receivedExam->is($exam) && $quantity === 3)
            ->andReturn($copies);
        $this->instance(ExamPrintService::class, $printService);

        $fakePdf = new class
        {
            public function output(): string
            {
                return '%PDF-fake-batch';
            }
        };
        $generator = Mockery::mock(AnswerSheetGeneratorService::class);
        $generator->shouldReceive('assertCompatible')
            ->once()
            ->withArgs(fn (Exam $receivedExam, OmrTemplate $receivedTemplate): bool => $receivedExam->is($exam) && $receivedTemplate->is($template));
        $generator->shouldReceive('generate')
            ->once()
            ->withArgs(function (Exam $receivedExam, $receivedCopies, OmrTemplate $receivedTemplate, string $mode) use ($exam, $template): bool {
                return $receivedExam->is($exam)
                    && $receivedCopies->count() === 3
                    && $receivedTemplate->is($template)
                    && $mode === 'hybrid';
            })
            ->andReturn(['pdf' => $fakePdf, 'template' => $template]);
        $this->instance(AnswerSheetGeneratorService::class, $generator);

        $resolver = Mockery::mock(ConfigPrecedenceResolver::class);
        $resolver->shouldReceive('resolveWithTrace')
            ->once()
            ->andReturn(['effective_value' => 'hybrid']);
        $this->instance(ConfigPrecedenceResolver::class, $resolver);

        $response = $this->actingAs($teacher)->post(route('exams.exportAnswerSheet', $exam), [
            'quantity' => 3,
            'card_template_id' => $template->id,
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="Cartoes_Resposta_'.$exam->id.'_3_copias.pdf"');
        $response->assertContent('%PDF-fake-batch');
    }
}
