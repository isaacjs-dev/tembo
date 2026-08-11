<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamCopy;
use App\Models\OmrTemplate;
use App\Models\OmrTemplateVersion;
use App\Models\Organization;
use App\Models\User;
use App\Services\PrintedQrBindingService;
use App\Services\QrCodeSigningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class QrPayloadContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_vectors_are_accepted_for_their_tenant_and_rejected_for_another(): void
    {
        $fixture = $this->fixture();
        config(['app.key' => data_get($fixture, 'signing_context.application_key')]);
        $organization = Organization::query()->create([
            'id' => data_get($fixture, 'signing_context.organization_id'),
            'name' => 'Tenant do contrato QR',
            'active' => true,
        ]);
        $other = Organization::query()->create(['name' => 'Outro tenant', 'active' => true]);
        $signer = app(QrCodeSigningService::class);

        foreach ($fixture['vectors'] as $vector) {
            $payload = $vector['payload'];

            $this->assertTrue($signer->hasSupportedContract($payload), $vector['name']);
            $this->assertTrue($signer->verifyPayload($payload, $organization->id), $vector['name']);
            $this->assertFalse($signer->verifyPayload($payload, $other->id), $vector['name']);
            $this->assertArrayNotHasKey('gab', $payload);
            $this->assertArrayNotHasKey('student_name', $payload);
            $this->assertArrayNotHasKey('organization_id', $payload);
        }
    }

    public function test_published_negative_vectors_fail_closed_even_when_resigned(): void
    {
        $organization = Organization::query()->create(['name' => 'Tenant QR inválido', 'active' => true]);
        $fixture = $this->fixture();
        $signer = app(QrCodeSigningService::class);
        $current = collect($fixture['vectors'])->firstWhere('name', 'current-v5')['payload'];
        unset($current['chk']);

        foreach ($fixture['invalid_vectors'] as $vector) {
            $payload = array_replace($current, $vector['patch']);
            $payload['chk'] = $signer->signPayload($payload, $organization->id);

            $this->assertFalse($signer->hasSupportedContract($payload), $vector['name']);
            $this->assertFalse($signer->verifyPayload($payload, $organization->id), $vector['name']);
        }

        $numericString = array_replace($current, ['e' => (string) $current['e']]);
        $numericString['chk'] = $signer->signPayload($numericString, $organization->id);
        $this->assertFalse($signer->hasSupportedContract($numericString));
    }

    public function test_binding_pins_template_version_page_options_and_exam_revision(): void
    {
        $organization = Organization::query()->create(['name' => 'Tenant binding QR', 'active' => true]);
        $teacher = User::factory()->create(['organization_id' => $organization->id]);
        $exam = Exam::query()->create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Avaliação versionada',
            'status' => 'published',
        ]);
        $template = OmrTemplate::query()->create([
            'name' => 'Template do contrato QR',
            'slug' => 'qr-contract-template',
            'organization_id' => $organization->id,
            'created_by' => $teacher->id,
            'visibility_scope' => 'org_public',
            'layout_config' => ['columns' => 1, 'rows_per_column' => 20],
            'max_questions' => 20,
            'max_columns' => 1,
            'columns' => 1,
            'rows_per_column' => 20,
            'max_options' => 5,
            'total_questions' => 20,
            'total_pages' => 1,
            'current_version' => 3,
            'width' => 2480,
            'height' => 3508,
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'corner_points_json' => [],
            'thresholds_json' => [],
            'is_active' => true,
        ]);
        OmrTemplateVersion::query()->create([
            'omr_template_id' => $template->id,
            'version' => 3,
            'layout_config' => $template->layout_config,
            'header_config' => [],
        ]);
        $copy = ExamCopy::query()->create([
            'exam_id' => $exam->id,
            'copy_number' => 1,
            'exam_version' => 2,
            'card_template_id' => $template->id,
            'card_template_version' => 3,
            'template_snapshot' => [
                'layout_config' => ['columns' => 1, 'rows_per_column' => 20],
            ],
            'questions_map' => [501, 502],
            'options_map' => [501 => [0, 1, 2, 3, 4], 502 => [0, 1]],
            'question_snapshot' => [
                ['id' => 501, 'type' => 'multiple_choice', 'content' => ['options' => ['A', 'B', 'C', 'D', 'E']]],
                ['id' => 502, 'type' => 'true_false', 'content' => []],
            ],
            'validation_hash' => 'immutable-copy-token-1234567890',
        ]);
        $signer = app(QrCodeSigningService::class);
        $payload = $signer->buildPayload([
            'e' => $exam->id,
            'c' => $copy->id,
            'h' => $copy->validation_hash,
            'p' => 1,
            'pt' => 1,
            'qs' => 1,
            'qe' => 2,
            'v' => 5,
            'rpp' => 20,
            'tpl_id' => $template->id,
            'tpl_v' => 3,
            'g' => [800, 900, 5000, 500, 220, 300],
            'oc' => '52',
        ], 'preloaded', $organization->id);

        $binding = app(PrintedQrBindingService::class)->bind(
            $payload,
            $organization->id,
            $exam,
            $copy,
            ['page' => 1, 'total_pages' => 1, 'question_start' => 1],
        );
        $this->assertSame(2, $binding['exam_version']);
        $this->assertSame($template->id, $binding['template_id']);
        $this->assertSame(3, $binding['template_version']);
        $this->assertFalse($binding['legacy_binding']);

        foreach ([['tpl_v' => 4], ['oc' => '55'], ['qs' => 2]] as $patch) {
            $invalid = array_replace($payload, $patch);
            unset($invalid['chk']);
            $invalid = $signer->buildPayload($invalid, 'preloaded', $organization->id);

            try {
                app(PrintedQrBindingService::class)->bind($invalid, $organization->id, $exam, $copy);
                $this->fail('O vínculo QR divergente deveria ser rejeitado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('qr_payload', $exception->errors());
            }
        }
    }

    /** @return array<string, mixed> */
    private function fixture(): array
    {
        return json_decode(
            file_get_contents(base_path('../contracts/omr/qr-contract.vectors.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
