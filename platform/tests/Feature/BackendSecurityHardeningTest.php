<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckOmrApiAccess;
use App\Models\AuditLog;
use App\Models\EventLog;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\OmrScan;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackendSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['global_admin', 'institution_admin', 'teacher', 'student'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_student_cannot_access_question_or_exam_management(): void
    {
        $student = $this->user($this->organization(), 'student');

        $this->actingAs($student)->get(route('questions.index'))->assertForbidden();
        $this->actingAs($student)->get(route('exams.create'))->assertForbidden();
    }

    public function test_teacher_cannot_access_institution_administration_routes(): void
    {
        $teacher = $this->user($this->organization(), 'teacher');

        foreach ([
            'institution.settings',
            'institution.billing.index',
            'institution.invites.index',
            'institution.teachers.index',
            'institution.students.index',
            'institution.roles.index',
            'institution.classes.index',
        ] as $routeName) {
            $this->actingAs($teacher)->get(route($routeName))->assertForbidden();
        }
    }

    public function test_exam_cannot_attach_private_question_from_another_institution(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $exam = $this->exam($organization, $teacher);

        $otherOrganization = $this->organization();
        $otherTeacher = $this->user($otherOrganization, 'teacher');
        $foreignQuestion = $this->question($otherOrganization, $otherTeacher, 'private');

        $this->actingAs($teacher)
            ->post(route('exams.addQuestion', $exam), [
                'question_id' => $foreignQuestion->id,
                'points' => 1,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('exam_questions', [
            'exam_id' => $exam->id,
            'question_id' => $foreignQuestion->id,
        ]);
    }

    public function test_exam_can_attach_public_question_from_same_institution(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $otherTeacher = $this->user($organization, 'teacher');
        $exam = $this->exam($organization, $teacher);
        $question = $this->question($organization, $otherTeacher, 'org_public');

        $this->actingAs($teacher)
            ->post(route('exams.addQuestion', $exam), [
                'question_id' => $question->id,
                'points' => 2,
            ])
            ->assertRedirect(route('exams.edit', $exam->id));

        $this->assertDatabaseHas('exam_questions', [
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'points' => 2,
        ]);
    }

    public function test_exam_cannot_sync_class_from_another_institution(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $exam = $this->exam($organization, $teacher);

        $otherOrganization = $this->organization();
        $foreignClass = SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $otherOrganization->id,
            'owner_type' => Organization::class,
            'owner_id' => $otherOrganization->id,
            'name' => 'Turma externa',
            'year' => '2026',
        ]);

        $this->actingAs($teacher)
            ->from(route('exams.edit', $exam))
            ->post(route('exams.syncClasses', $exam), ['class_ids' => [$foreignClass->id]])
            ->assertSessionHasErrors('class_ids');

        $this->assertDatabaseMissing('exam_school_class', [
            'exam_id' => $exam->id,
            'school_class_id' => $foreignClass->id,
        ]);
    }

    public function test_answer_sheet_export_checks_authorship_before_input_validation(): void
    {
        $organization = $this->organization();
        $author = $this->user($organization, 'teacher');
        $otherTeacher = $this->user($organization, 'teacher');
        $exam = $this->exam($organization, $author);

        $this->actingAs($otherTeacher)
            ->post(route('exams.exportAnswerSheet', $exam), [])
            ->assertNotFound();
    }

    public function test_institution_logs_are_filtered_by_organization(): void
    {
        $organization = $this->organization(['can_access_logs' => true]);
        $admin = $this->user($organization, 'institution_admin');
        $otherOrganization = $this->organization(['can_access_logs' => true]);

        EventLog::create([
            'organization_id' => $organization->id,
            'actor_user_id' => $admin->id,
            'event_code' => 'own.event',
            'severity' => 'info',
            'message' => 'Evento visível',
        ]);
        EventLog::create([
            'organization_id' => $otherOrganization->id,
            'event_code' => 'foreign.event',
            'severity' => 'critical',
            'message' => 'Evento secreto',
        ]);

        $this->actingAs($admin)
            ->get(route('institution.logs'))
            ->assertOk()
            ->assertSee('own.event')
            ->assertDontSee('foreign.event')
            ->assertViewHas('logs', fn ($logs) => $logs->every(
                fn (EventLog $log) => $log->organization_id === $organization->id
            ));
    }

    public function test_exam_can_be_restored_from_institution_trash_only_in_its_tenant(): void
    {
        $organization = $this->organization(['can_access_trash' => true]);
        $admin = $this->user($organization, 'institution_admin');
        $exam = $this->exam($organization, $admin);
        $exam->delete();

        $this->actingAs($admin)
            ->get(route('institution.trash.index'))
            ->assertOk()
            ->assertSee($exam->title);

        $this->actingAs($admin)
            ->post(route('institution.trash.restore'), [
                'model_type' => 'exam',
                'model_id' => $exam->id,
            ])
            ->assertRedirect();

        $this->assertNotSoftDeleted('exams', ['id' => $exam->id]);
    }

    public function test_inactive_user_cannot_log_in_on_web_or_api(): void
    {
        $user = $this->user($this->organization(), 'teacher', ['status' => 'inactive']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'Regression test',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_exam_scanner_api_requires_the_omr_entitlement(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/exams')->assertForbidden();
    }

    public function test_exam_scanner_api_only_lists_and_downloads_owned_published_exams(): void
    {
        $organization = $this->organization();
        $this->enableOmr($organization);
        $teacher = $this->user($organization, 'teacher');
        $otherTeacher = $this->user($organization, 'teacher');
        $ownedExam = $this->exam($organization, $teacher);
        $ownedExam->update(['status' => 'published']);
        $foreignExam = $this->exam($organization, $otherTeacher);
        $foreignExam->update(['status' => 'published']);
        $draftExam = $this->exam($organization, $teacher);

        Sanctum::actingAs($teacher);

        $this->getJson('/api/v1/exams')
            ->assertOk()
            ->assertJsonCount(1, 'exams')
            ->assertJsonPath('exams.0.id', $ownedExam->id);

        $this->getJson("/api/v1/exams/{$ownedExam->id}/download")
            ->assertOk()
            ->assertJsonPath('exam.id', $ownedExam->id);
        $this->getJson("/api/v1/exams/{$foreignExam->id}/download")->assertNotFound();
        $this->getJson("/api/v1/exams/{$draftExam->id}/download")->assertNotFound();
    }

    public function test_auditable_redacts_password_tokens_and_nested_json_secrets(): void
    {
        $user = User::factory()->create(['remember_token' => 'remember-me']);
        $userLog = AuditLog::where('action', 'created')
            ->where('model_type', User::class)
            ->where('model_id', $user->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('[REDACTED]', data_get($userLog->payload, 'new.password'));
        $this->assertSame('[REDACTED]', data_get($userLog->payload, 'new.remember_token'));

        $organization = $this->organization([
            'settings' => ['api_token' => 'never-log-this', 'theme' => 'green'],
        ]);
        $organizationLog = AuditLog::where('action', 'created')
            ->where('model_type', Organization::class)
            ->where('model_id', $organization->id)
            ->latest('id')
            ->firstOrFail();
        $settings = json_decode(data_get($organizationLog->payload, 'new.settings'), true);

        $this->assertSame('[REDACTED]', $settings['api_token']);
        $this->assertSame('green', $settings['theme']);
        $this->assertStringNotContainsString('never-log-this', json_encode($organizationLog->payload));
    }

    public function test_teacher_cannot_manage_api_configuration_and_admin_cannot_trace_foreign_user(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');

        Sanctum::actingAs($teacher);
        $this->getJson('/api/v2/config/rules')->assertForbidden();

        $admin = $this->user($organization, 'institution_admin');
        $foreignUser = $this->user($this->organization(), 'teacher');
        Sanctum::actingAs($admin);

        $this->getJson("/api/v2/config/trace/{$foreignUser->id}")->assertNotFound();
    }

    public function test_omr_upload_rejects_exam_from_another_institution(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $otherOrganization = $this->organization();
        $otherTeacher = $this->user($otherOrganization, 'teacher');
        $foreignExam = $this->exam($otherOrganization, $otherTeacher);

        Sanctum::actingAs($teacher);
        $this->withoutMiddleware(CheckOmrApiAccess::class)
            ->postJson('/api/v1/omr/scans', [
                'session_id' => (string) Str::uuid(),
                'exam_id' => $foreignExam->id,
                'page_index' => 1,
                'total_pages' => 1,
                'image' => UploadedFile::fake()->image('page.jpg'),
                'detected_answers' => json_encode([]),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('exam_id');
    }

    public function test_omr_upload_persists_and_consolidates_only_in_authenticated_tenant(): void
    {
        Storage::fake('public');
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $exam = $this->exam($organization, $teacher);
        $sessionId = (string) Str::uuid();

        Sanctum::actingAs($teacher);
        $this->withoutMiddleware(CheckOmrApiAccess::class)
            ->post('/api/v1/omr/scans', [
                'session_id' => $sessionId,
                'exam_id' => $exam->id,
                'page_index' => 1,
                'total_pages' => 1,
                'image' => UploadedFile::fake()->image('page.jpg'),
                'detected_answers' => json_encode([]),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('progress.is_complete', true);

        $this->assertDatabaseHas('omr_scan_pages', [
            'organization_id' => $organization->id,
            'uploaded_by' => $teacher->id,
            'session_id' => $sessionId,
            'status' => 'consolidated',
        ]);
        $this->assertDatabaseHas('omr_scans', [
            'organization_id' => $organization->id,
            'uploaded_by' => $teacher->id,
            'session_id' => $sessionId,
            'status' => 'processed',
        ]);
    }

    public function test_omr_confirm_returns_submission_from_grading_result_contract(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $exam = $this->exam($organization, $teacher);
        $question = $this->question($organization, $teacher, 'private');
        $exam->questions()->attach($question->id, ['points' => 2, 'order' => 1]);

        $scan = OmrScan::create([
            'organization_id' => $organization->id,
            'exam_id' => $exam->id,
            'uploaded_by' => $teacher->id,
            'student_id' => $student->id,
            'image_path' => 'omr/test.jpg',
            'idempotency_key' => 'test-'.uniqid(),
            'status' => 'processed',
            'source' => 'mobile',
            'detected_answers' => [$question->id => 0],
        ]);

        Sanctum::actingAs($teacher);
        $response = $this->withoutMiddleware(CheckOmrApiAccess::class)
            ->putJson("/api/v1/omr/scans/{$scan->id}/confirm", [
                'student_id' => $student->id,
                'confirmed_answers' => [$question->id => 0],
            ])
            ->assertOk()
            ->assertJsonPath('submission.status', 'graded')
            ->assertJsonPath('submission.score', '2.00');

        $this->assertNotNull($response->json('submission.id'));
    }

    public function test_omr_grading_creates_numbered_attempt_without_overwriting_finished_online_attempt(): void
    {
        $organization = $this->organization();
        $teacher = $this->user($organization, 'teacher');
        $student = $this->user($organization, 'student');
        $exam = $this->exam($organization, $teacher);
        $question = $this->question($organization, $teacher, 'private');
        $exam->questions()->attach($question->id, ['points' => 2, 'order' => 1]);

        $onlineAttempt = ExamSubmission::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'started_at' => now()->subMinutes(10),
            'finished_at' => now()->subMinutes(5),
            'client_token' => (string) Str::uuid(),
            'score' => 0,
        ]);
        ExamAnswer::create([
            'exam_submission_id' => $onlineAttempt->id,
            'question_id' => $question->id,
            'answer_data' => ['raw' => 1],
            'is_correct' => false,
            'points_awarded' => 0,
        ]);

        $scan = OmrScan::create([
            'organization_id' => $organization->id,
            'exam_id' => $exam->id,
            'uploaded_by' => $teacher->id,
            'student_id' => $student->id,
            'image_path' => 'omr/attempt-test.jpg',
            'idempotency_key' => 'attempt-test-'.uniqid(),
            'status' => 'processed',
            'source' => 'mobile',
            'detected_answers' => [$question->id => 0],
        ]);

        Sanctum::actingAs($teacher);
        $response = $this->withoutMiddleware(CheckOmrApiAccess::class)
            ->putJson("/api/v1/omr/scans/{$scan->id}/confirm", [
                'student_id' => $student->id,
                'confirmed_answers' => [$question->id => 0],
            ])
            ->assertOk()
            ->assertJsonPath('submission.score', '2.00');

        $omrAttempt = ExamSubmission::findOrFail($response->json('submission.id'));
        $this->assertNotSame($onlineAttempt->id, $omrAttempt->id);
        $this->assertSame(2, $omrAttempt->attempt_number);
        $this->assertSame('graded', $omrAttempt->status);
        $this->assertNotNull($omrAttempt->client_token);
        $this->assertSame('0.00', $onlineAttempt->fresh()->score);
        $this->assertSame(0, (int) $onlineAttempt->answers()->firstOrFail()->points_awarded);
        $this->assertSame($omrAttempt->id, $scan->fresh()->exam_submission_id);

        // Retrying the same scan/answers is idempotent and does not create attempt 3.
        $this->withoutMiddleware(CheckOmrApiAccess::class)
            ->putJson("/api/v1/omr/scans/{$scan->id}/confirm", [
                'student_id' => $student->id,
                'confirmed_answers' => [$question->id => 0],
            ])
            ->assertOk()
            ->assertJsonPath('submission.id', $omrAttempt->id);

        $this->assertSame(
            2,
            ExamSubmission::where('exam_id', $exam->id)->where('user_id', $student->id)->count()
        );
    }

    private function organization(array $attributes = []): Organization
    {
        return Organization::create(array_merge([
            'name' => 'Organization '.uniqid(),
            'active' => true,
        ], $attributes));
    }

    private function user(
        Organization $organization,
        string $type,
        array $attributes = []
    ): User {
        $user = User::create(array_merge([
            'organization_id' => $organization->id,
            'name' => ucfirst($type),
            'email' => $type.'-'.uniqid().'@example.test',
            'password' => 'password',
            'type' => $type,
            'status' => 'active',
        ], $attributes));
        $user->assignRole($type);

        return $user;
    }

    private function exam(Organization $organization, User $author): Exam
    {
        return Exam::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'author_id' => $author->id,
            'title' => 'Assessment '.uniqid(),
            'status' => 'draft',
            'settings' => [],
        ]);
    }

    private function question(
        Organization $organization,
        User $owner,
        string $visibility
    ): Question {
        return Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $owner->id,
            'type' => 'multiple_choice',
            'content' => [
                'statement' => 'Question '.uniqid(),
                'options' => ['A', 'B'],
                'correct_option' => 0,
            ],
            'visibility_scope' => $visibility,
        ]);
    }

    private function enableOmr(Organization $organization): void
    {
        $plan = Plan::create([
            'name' => 'OMR',
            'slug' => 'omr-'.uniqid(),
            'price' => 1,
            'tier_level' => 1,
            'status' => 'active',
        ]);
        PlanFeature::create([
            'plan_id' => $plan->id,
            'feature_key' => 'omr',
            'enabled' => true,
        ]);
        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
        ]);
    }
}
