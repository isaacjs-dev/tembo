<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use App\Models\OmrTemplateVersion;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use App\Services\AnswerSheetGeneratorService;
use App\Services\ExamPrintService;
use App\Services\QrCodeSigningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OmrPrintPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_generation_preserves_complete_maps_for_one_and_three_copies(): void
    {
        [$exam, $questions] = $this->makeExamWithMixedQuestions();
        $service = app(ExamPrintService::class);

        $single = $service->generateCopies($exam, 1);

        $this->assertCount(1, $single);
        $this->assertSame($questions->pluck('id')->all(), $single->first()->questions_map);
        $this->assertSame([0, 1, 2, 3], $single->first()->options_map[$questions[0]->id]);
        $this->assertSame([0, 1], $single->first()->options_map[$questions[1]->id]);
        $this->assertNull($single->first()->options_map[$questions[2]->id]);
        $this->assertSame(40, strlen($single->first()->validation_hash));

        $batch = $service->generateCopies($exam, 3, [
            'shuffle_questions' => true,
            'shuffle_options_mc' => true,
            'shuffle_options_tf' => true,
            'group_disciplines' => false,
        ]);

        $this->assertCount(3, $batch);
        $this->assertCount(3, $batch->pluck('validation_hash')->unique());
        foreach ($batch as $copy) {
            $this->assertEqualsCanonicalizing($questions->pluck('id')->all(), $copy->questions_map);
            $this->assertEqualsCanonicalizing([0, 1, 2, 3], $copy->options_map[$questions[0]->id]);
            $this->assertEqualsCanonicalizing([0, 1], $copy->options_map[$questions[1]->id]);
            $this->assertNull($copy->options_map[$questions[2]->id]);
        }
    }

    public function test_mixed_card_has_only_real_bubbles_and_a_signed_encrypted_answer_key(): void
    {
        [$exam, $questions] = $this->makeExamWithMixedQuestions();
        $template = $this->makeTemplate($exam, 20, 2, 10);
        $copy = $this->makeCopy($exam, $questions, [
            $questions[0]->id => [2, 0, 1, 3],
            $questions[1]->id => [1, 0],
            $questions[2]->id => null,
        ]);

        $pages = app(AnswerSheetGeneratorService::class)
            ->buildCardPages($exam, $copy, $template, 'hybrid');

        $this->assertCount(1, $pages);
        $this->assertSame([4, 2, 0], collect($pages[0]['geometry']['cells'])->map(
            fn (array $cell): int => count($cell['bubbles'])
        )->all());
        $this->assertSame([false, false, true], collect($pages[0]['geometry']['cells'])->pluck('essay')->all());
        $this->assertSame('420', $pages[0]['qrPayload']['oc']);
        $this->assertSame(5, $pages[0]['qrPayload']['v']);
        $this->assertArrayNotHasKey('gab', $pages[0]['qrPayload']);
        $this->assertArrayHasKey('gab_enc', $pages[0]['qrPayload']);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{22}$/', $pages[0]['qrPayload']['chk']);

        $signer = app(QrCodeSigningService::class);
        $this->assertTrue($signer->verifyPayload($pages[0]['qrPayload'], $exam->organization_id));
        $this->assertSame(
            [0, 1, -1],
            $signer->decryptGabarito($pages[0]['qrPayload']['gab_enc'], $exam->organization_id)
        );

        $tampered = $pages[0]['qrPayload'];
        $tampered['g'][0]++;
        $this->assertFalse($signer->verifyPayload($tampered, $exam->organization_id));
    }

    public function test_preloaded_qr_contains_no_plain_or_encrypted_answer_key(): void
    {
        [$exam, $questions] = $this->makeExamWithMixedQuestions();
        $template = $this->makeTemplate($exam, 20, 2, 10);
        $copy = $this->makeCopy($exam, $questions);

        $payload = app(AnswerSheetGeneratorService::class)
            ->buildCardPages($exam, $copy, $template, 'preloaded')[0]['qrPayload'];

        $this->assertArrayNotHasKey('gab', $payload);
        $this->assertArrayNotHasKey('gab_enc', $payload);
        $this->assertArrayNotHasKey('pts', $payload);
        $this->assertTrue(app(QrCodeSigningService::class)->verifyPayload($payload, $exam->organization_id));
    }

    public function test_qr_verification_rejects_another_tenant_but_keeps_v3_compatibility(): void
    {
        [$exam] = $this->makeExam();
        [, $otherOrganization] = $this->makeExam();
        $signer = app(QrCodeSigningService::class);
        $payload = $signer->buildPayload([
            'e' => $exam->id,
            'c' => 10,
            'h' => 'hash',
            'p' => 1,
            'v' => 4,
            'tpl_id' => 5,
            'tpl_v' => 2,
            'g' => [1, 2, 3, 4, 5, 6],
            'gab' => [0, 1],
        ], 'hybrid', $exam->organization_id);

        $this->assertTrue($signer->verifyPayload($payload, $exam->organization_id));
        $this->assertFalse($signer->verifyPayload($payload, $otherOrganization->id));

        $legacy = [
            'e' => $exam->id,
            'c' => 10,
            'h' => 'legacy-hash',
            'p' => 1,
            'v' => 3,
            'tpl_id' => 5,
            'tpl_v' => 1,
            'gab_enc' => 'legacy-ciphertext',
        ];
        $legacyCanonical = implode('|', [
            $legacy['e'],
            $legacy['c'],
            $legacy['h'],
            $legacy['p'],
            $legacy['tpl_id'],
            $legacy['tpl_v'],
            $legacy['gab_enc'],
        ]);
        $legacyKey = hash(
            'sha256',
            config('app.key').'|omr|'.$exam->organization_id,
            true
        );
        $legacy['chk'] = substr(hash_hmac('sha256', $legacyCanonical, $legacyKey), 0, 16);

        $this->assertTrue($signer->verifyPayload($legacy, $exam->organization_id));

        // Cartões v4 já impressos continuam válidos: usavam HMAC em hexadecimal.
        $v4 = $signer->buildPayload([
            'e' => $exam->id,
            'c' => 11,
            'h' => 'existing-card',
            'p' => 1,
            'v' => 4,
            'tpl' => 'legacy-professional',
            'tpl_id' => 5,
            'tpl_v' => 2,
            'g' => [1, 2, 3, 4, 5, 6],
        ], 'preloaded', $exam->organization_id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $v4['chk']);
        $this->assertTrue($signer->verifyPayload($v4, $exam->organization_id));
    }

    public function test_qr_contract_rejects_unknown_versions_fields_and_unsigned_downgrades(): void
    {
        [$exam] = $this->makeExam();
        $signer = app(QrCodeSigningService::class);
        $payload = $signer->buildPayload([
            'e' => $exam->id,
            'c' => 10,
            'h' => 'hash',
            'p' => 1,
            'pt' => 1,
            'qs' => 1,
            'qe' => 1,
            'v' => 5,
            'rpp' => 20,
            'tpl_id' => 5,
            'tpl_v' => 2,
            'g' => [1, 2, 3, 4, 5, 6],
            'oc' => '2',
        ], 'preloaded', $exam->organization_id);

        $unknownField = $payload;
        $unknownField['student_name'] = 'Não deve entrar no QR';
        $unknownField['chk'] = $signer->signPayload($unknownField, $exam->organization_id);
        $this->assertFalse($signer->verifyPayload($unknownField, $exam->organization_id));

        $unknownVersion = $payload;
        $unknownVersion['v'] = 6;
        $unknownVersion['chk'] = $signer->signPayload($unknownVersion, $exam->organization_id);
        $this->assertFalse($signer->verifyPayload($unknownVersion, $exam->organization_id));

        $unsignedLegacy = ['e' => $exam->id, 'c' => 10, 'h' => 'hash', 'p' => 1, 'v' => 3, 'tpl_id' => 5, 'tpl_v' => 1];
        $this->assertFalse($signer->verifyPayload($unsignedLegacy, $exam->organization_id));
    }

    public function test_templates_support_twenty_fifty_and_custom_multi_page_limits(): void
    {
        [$exam20, $questions20] = $this->makeObjectiveExam(20);
        $template20 = $this->makeTemplate($exam20, 20, 2, 10);
        $pages20 = app(AnswerSheetGeneratorService::class)->buildCardPages(
            $exam20,
            $this->makeCopy($exam20, $questions20),
            $template20
        );
        $this->assertCount(1, $pages20);
        $this->assertSame([1, 20], [$pages20[0]['qStart'], $pages20[0]['qEnd']]);

        [$exam50, $questions50] = $this->makeObjectiveExam(50);
        $template50 = $this->makeTemplate($exam50, 50, 4, 13);
        $pages50 = app(AnswerSheetGeneratorService::class)->buildCardPages(
            $exam50,
            $this->makeCopy($exam50, $questions50),
            $template50
        );
        $this->assertCount(1, $pages50);
        $this->assertSame([1, 50], [$pages50[0]['qStart'], $pages50[0]['qEnd']]);

        [$customExam, $customQuestions] = $this->makeObjectiveExam(100);
        $customTemplate = $this->makeTemplate($customExam, 100, 2, 10);
        $customPages = app(AnswerSheetGeneratorService::class)->buildCardPages(
            $customExam,
            $this->makeCopy($customExam, $customQuestions),
            $customTemplate
        );
        $this->assertCount(5, $customPages);
        $this->assertSame(
            [[1, 20], [21, 40], [41, 60], [61, 80], [81, 100]],
            collect($customPages)->map(fn (array $page): array => [$page['qStart'], $page['qEnd']])->all()
        );
        $this->assertSame([1, 2, 3, 4, 5], collect($customPages)->pluck('page')->all());
        $this->assertSame([5, 5, 5, 5, 5], collect($customPages)->pluck('totalPages')->all());
    }

    public function test_template_limit_is_enforced(): void
    {
        [$exam, $questions] = $this->makeObjectiveExam(21);
        $template = $this->makeTemplate($exam, 20, 2, 10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('acima do limite');

        app(AnswerSheetGeneratorService::class)
            ->buildCardPages($exam, $this->makeCopy($exam, $questions), $template);
    }

    public function test_invalid_shuffle_map_is_rejected_instead_of_generating_an_ambiguous_card(): void
    {
        [$exam, $questions] = $this->makeExamWithMixedQuestions();
        $template = $this->makeTemplate($exam, 20, 2, 10);
        $copy = $this->makeCopy($exam, $questions, [
            $questions[0]->id => [0, 0, 1, 2],
            $questions[1]->id => [0, 1],
            $questions[2]->id => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mapa de alternativas');

        app(AnswerSheetGeneratorService::class)->buildCardPages($exam, $copy, $template);
    }

    public function test_template_that_would_clip_the_grid_is_rejected(): void
    {
        [$exam, $questions] = $this->makeObjectiveExam(40);
        $layout = $this->layout(100, 1, 60);
        $template = $this->makeTemplate($exam, 100, 1, 60, $layout);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('área segura');

        app(AnswerSheetGeneratorService::class)
            ->buildCardPages($exam, $this->makeCopy($exam, $questions), $template);
    }

    public function test_exam_uses_its_locked_template_version_for_reproducible_pages(): void
    {
        [$exam, $questions] = $this->makeObjectiveExam(25);
        $versionOneLayout = $this->layout(40, 2, 10);
        $versionTwoLayout = $this->layout(50, 2, 25);
        $template = $this->makeTemplate($exam, 50, 2, 25, $versionTwoLayout);
        $template->update(['current_version' => 2]);
        OmrTemplateVersion::create([
            'omr_template_id' => $template->id,
            'version' => 1,
            'layout_config' => $versionOneLayout,
            'header_config' => [],
        ]);
        OmrTemplateVersion::create([
            'omr_template_id' => $template->id,
            'version' => 2,
            'layout_config' => $versionTwoLayout,
            'header_config' => [],
        ]);
        $exam->update([
            'card_template_id' => $template->id,
            'card_template_version' => 1,
        ]);

        $pages = app(AnswerSheetGeneratorService::class)
            ->buildCardPages($exam->fresh(), $this->makeCopy($exam, $questions), $template->fresh());

        $this->assertCount(2, $pages);
        $this->assertSame(1, $pages[0]['qrPayload']['tpl_v']);
        $this->assertSame([1, 20], [$pages[0]['qStart'], $pages[0]['qEnd']]);
        $this->assertSame([21, 25], [$pages[1]['qStart'], $pages[1]['qEnd']]);

        $pagesWithBatchPlacement = app(AnswerSheetGeneratorService::class)->buildCardPages(
            $exam->fresh(),
            $this->makeCopy($exam, $questions),
            $template->fresh(),
            'hybrid',
            array_replace($versionTwoLayout, [
                'frame_left_mm' => 8,
                'frame_top_mm' => 54,
                'frame_width_mm' => 174,
            ])
        );
        $this->assertCount(2, $pagesWithBatchPlacement);
        $this->assertSame(1, $pagesWithBatchPlacement[0]['qrPayload']['tpl_v']);
    }

    public function test_real_pdf_contains_one_page_per_copy_and_card_page(): void
    {
        [$exam, $questions] = $this->makeObjectiveExam(25);
        $template = $this->makeTemplate($exam, 50, 2, 10);
        $copies = app(ExamPrintService::class)->generateCopies($exam, 3);

        $singleResult = app(AnswerSheetGeneratorService::class)
            ->generate($exam, $copies->take(1), $template);
        $singleBytes = $singleResult['pdf']->output();
        $this->assertStringStartsWith('%PDF-', $singleBytes);
        $this->assertSame(2, $singleResult['pdf']->getDomPDF()->getCanvas()->get_page_count());

        $result = app(AnswerSheetGeneratorService::class)->generate($exam, $copies, $template);
        $pdfBytes = $result['pdf']->output();
        $pageCount = $result['pdf']->getDomPDF()->getCanvas()->get_page_count();

        $this->assertStringStartsWith('%PDF-', $pdfBytes);
        $this->assertSame(6, $pageCount);

        $artifactDirectory = storage_path('app/qa');
        File::ensureDirectoryExists($artifactDirectory);
        File::put($artifactDirectory.'/omr-answer-sheets-3-copies.pdf', $pdfBytes);
        $this->assertFileExists($artifactDirectory.'/omr-answer-sheets-3-copies.pdf');
    }

    public function test_advanced_print_pdf_keeps_exam_card_and_teacher_key_separated(): void
    {
        [$exam, $questions] = $this->makeExamWithMixedQuestions();
        $template = $this->makeTemplate($exam, 20, 2, 10);
        $copy = app(ExamPrintService::class)->generateCopies($exam, 1)->first();
        $cardPages = app(AnswerSheetGeneratorService::class)
            ->buildCardPages($exam, $copy, $template, 'hybrid', [
                'frame_left_mm' => 8,
                'frame_top_mm' => 54,
                'frame_width_mm' => 174,
            ]);

        $pdf = Pdf::loadView('exams.pdf_advanced', [
            'exam' => $exam,
            'copies' => collect([$copy]),
            'calibration' => ['offset_x' => 0, 'offset_y' => 0, 'scale' => 90],
            'options' => [],
            'cardPagesByCopy' => [$copy->id => $cardPages],
        ]);
        $bytes = $pdf->output();

        $this->assertStringStartsWith('%PDF-', $bytes);
        $this->assertSame(3, $pdf->getDomPDF()->getCanvas()->get_page_count());
        $this->assertCount(1, $cardPages);
        $this->assertSame($questions->pluck('id')->all(), $copy->questions_map);
    }

    private function makeExamWithMixedQuestions(): array
    {
        [$exam] = $this->makeExam();
        $questions = collect([
            $this->makeQuestion($exam, 'multiple_choice', [
                'statement' => 'Questão objetiva',
                'options' => ['A1', 'A2', 'A3', 'A4'],
                'correct_option' => 2,
            ]),
            $this->makeQuestion($exam, 'true_false', [
                'statement' => 'Questão de verdadeiro ou falso',
                'correct_option' => 0,
            ]),
            $this->makeQuestion($exam, 'essay', [
                'statement' => 'Questão discursiva',
            ]),
        ]);

        return [$exam->fresh('questions'), $questions];
    }

    private function makeObjectiveExam(int $count): array
    {
        [$exam] = $this->makeExam();
        $questions = collect();
        for ($index = 1; $index <= $count; $index++) {
            $questions->push($this->makeQuestion($exam, 'multiple_choice', [
                'statement' => "Questão {$index}",
                'options' => ['A', 'B', 'C', 'D', 'E'],
                'correct_option' => $index % 5,
            ]));
        }

        return [$exam->fresh('questions'), $questions];
    }

    private function makeExam(): array
    {
        $organization = Organization::create([
            'name' => 'Escola OMR',
            'active' => true,
        ]);
        $teacher = User::factory()->create([
            'organization_id' => $organization->id,
            'type' => 'teacher',
            'status' => 'active',
        ]);
        $exam = Exam::create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação impressa',
            'status' => 'published',
            'settings' => ['application_mode' => 'paper'],
        ]);

        return [$exam, $organization, $teacher];
    }

    private function makeQuestion(Exam $exam, string $type, array $content): Question
    {
        $question = Question::create([
            'organization_id' => $exam->organization_id,
            'owner_id' => $exam->author_id,
            'type' => $type,
            'content' => $content,
            'visibility_scope' => 'private',
            'level' => 'medium',
        ]);
        $order = $exam->questions()->count() + 1;
        $exam->questions()->attach($question->id, ['points' => 1, 'order' => $order]);

        return $question;
    }

    private function makeCopy(Exam $exam, Collection $questions, ?array $optionsMap = null): ExamCopy
    {
        $optionsMap ??= $questions->mapWithKeys(function (Question $question): array {
            $map = match ($question->type) {
                'multiple_choice' => range(0, count($question->content['options']) - 1),
                'true_false' => [0, 1],
                default => null,
            };

            return [$question->id => $map];
        })->all();

        $copyNumber = ExamCopy::query()->where('exam_id', $exam->id)->count() + 1;

        return ExamCopy::create([
            'exam_id' => $exam->id,
            'copy_number' => $copyNumber,
            'questions_map' => $questions->pluck('id')->all(),
            'options_map' => $optionsMap,
            'validation_hash' => substr(
                hash('sha256', $exam->id.'|'.$copyNumber.'|'.$questions->pluck('id')->implode(',')),
                0,
                40
            ),
        ]);
    }

    private function makeTemplate(
        Exam $exam,
        int $maxQuestions,
        int $columns,
        int $rowsPerColumn,
        ?array $layout = null
    ): OmrTemplate {
        $layout ??= $this->layout($maxQuestions, $columns, $rowsPerColumn);

        return OmrTemplate::create([
            'name' => "Template {$maxQuestions}",
            'slug' => 'template-'.$exam->id.'-'.$maxQuestions.'-'.$columns.'-'.$rowsPerColumn,
            'organization_id' => $exam->organization_id,
            'created_by' => $exam->author_id,
            'visibility_scope' => 'org_public',
            'layout_config' => $layout,
            'max_questions' => $maxQuestions,
            'max_columns' => $columns,
            'columns' => $columns,
            'rows_per_column' => $rowsPerColumn,
            'max_options' => 5,
            'total_questions' => $maxQuestions,
            'total_pages' => 1,
            'current_version' => 1,
            'width' => 2480,
            'height' => 3508,
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'corner_points_json' => [],
            'thresholds_json' => [],
            'is_active' => true,
        ]);
    }

    private function layout(int $maxQuestions, int $columns, int $rowsPerColumn): array
    {
        return [
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'max_questions' => $maxQuestions,
            'columns' => $columns,
            'rows_per_column' => $rowsPerColumn,
            'max_options' => 5,
            'bubble_diameter_mm' => 4.2,
            'frame_fiducial_mm' => 8,
            'frame_left_mm' => 10,
            'frame_top_mm' => 50,
            'frame_width_mm' => 190,
            'row_spacing_mm' => 8,
            'cell_indent_mm' => 9,
            'grid_pad_top_mm' => 6,
            'option_gap_mm' => 1.5,
        ];
    }
}
