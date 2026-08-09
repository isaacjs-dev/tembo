<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\LearningMaterial;
use App\Models\OmrScan;
use App\Models\Organization;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_builds_complete_related_and_tenant_safe_scenarios(): void
    {
        $this->seed(DatabaseSeeder::class);

        $horizonte = Organization::where('subdomain', 'escola-modelo')->firstOrFail();
        $aurora = Organization::where('subdomain', 'colegio-aurora')->firstOrFail();
        $suspended = Organization::where('subdomain', 'instituicao-inativa')->firstOrFail();

        $this->assertTrue($horizonte->active);
        $this->assertTrue($aurora->active);
        $this->assertFalse($suspended->active);
        $this->assertSame(3, Organization::count());

        foreach ([
            'admin@admin.com',
            'institution@email.com',
            'coordinator@email.com',
            'teacher@email.com',
            'teacher.portugues@email.com',
            'student@email.com',
            'guardian@email.com',
            'institution.aurora@email.com',
            'teacher.aurora@email.com',
            'student01.aurora@demo.avaliation.test',
            'institution.inactive@email.com',
        ] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $this->assertTrue(Hash::check('password', $user->password), $email);
        }

        $teacher = User::where('email', 'teacher@email.com')->firstOrFail();
        $coordinator = User::where('email', 'coordinator@email.com')->firstOrFail();
        $student = User::where('email', 'student@email.com')->firstOrFail();

        $this->assertGreaterThanOrEqual(
            3,
            SchoolClass::withoutGlobalScopes()
                ->where('organization_id', $horizonte->id)
                ->whereHas('teachers', fn ($query) => $query->whereKey($teacher->id))
                ->count()
        );
        $this->assertDatabaseHas('institution_roles', [
            'organization_id' => $horizonte->id,
            'slug' => 'coordenador-pedagogico',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('user_organization', [
            'user_id' => $coordinator->id,
            'organization_id' => $horizonte->id,
            'status' => 'active',
        ]);

        foreach (['1º Ano A', '1º Ano B', '2º Ano A'] as $className) {
            $this->assertDatabaseHas('school_classes', [
                'organization_id' => $horizonte->id,
                'name' => $className,
            ]);
        }

        $this->assertGreaterThanOrEqual(2000, Question::withoutGlobalScopes()
            ->where('organization_id', $horizonte->id)
            ->count());
        $this->assertGreaterThanOrEqual(5, Exam::withoutGlobalScopes()
            ->where('organization_id', $horizonte->id)
            ->count());
        $this->assertDatabaseHas('exam_submissions', ['status' => 'graded']);
        $this->assertDatabaseHas('exam_submissions', ['status' => 'submitted']);
        $this->assertDatabaseHas('exam_submissions', ['status' => 'in_progress']);
        $this->assertGreaterThan(
            0,
            ExamSubmission::withoutGlobalScopes()->where('user_id', $student->id)->count()
        );

        foreach (['confirmed', 'reviewing', 'pending', 'rejected', 'synced'] as $status) {
            $this->assertDatabaseHas('omr_scans', [
                'organization_id' => $horizonte->id,
                'status' => $status,
            ]);
        }
        $this->assertGreaterThan(0, OmrScan::where('organization_id', $horizonte->id)->count());
        $this->assertDatabaseHas('learning_materials', [
            'organization_id' => $horizonte->id,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('learning_material_progress', [
            'student_id' => $student->id,
            'status' => 'completed',
        ]);
        $this->assertGreaterThan(
            0,
            LearningMaterial::withoutGlobalScopes()
                ->where('organization_id', $horizonte->id)
                ->count()
        );

        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $horizonte->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $horizonte->id,
            'status' => 'canceled',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'organization_id' => $suspended->id,
            'status' => 'past_due',
        ]);
        $this->assertGreaterThanOrEqual(3, Subscription::count());
        $this->assertDatabaseHas('event_logs', ['event_code' => 'billing.payment_pending']);
        $this->assertDatabaseHas('event_logs', ['event_code' => 'billing.courtesy_granted']);

        $this->assertDatabaseHas('lessons', ['organization_id' => $horizonte->id, 'status' => 'published']);
        $this->assertDatabaseHas('activities', ['organization_id' => $horizonte->id, 'status' => 'published']);
        foreach (['published', 'draft', 'in_review', 'changes_requested', 'suspended'] as $status) {
            $this->assertDatabaseHas('revisions', ['organization_id' => $horizonte->id, 'status' => $status]);
        }
        foreach (['multiple_choice', 'true_false', 'matching', 'fill_blank', 'ordering', 'flashcard', 'short_answer'] as $type) {
            $this->assertDatabaseHas('revision_items', ['type' => $type]);
        }
        $this->assertDatabaseHas('revision_attempts', ['status' => 'completed']);
        $this->assertDatabaseHas('revision_attempts', ['status' => 'in_progress']);
        $this->assertDatabaseHas('student_gamification_profiles', ['student_id' => $student->id]);
        $this->assertDatabaseHas('courtesy_grants', ['target_scope' => 'organization', 'target_id' => $horizonte->id, 'status' => 'active']);
        $this->assertDatabaseHas('usage_events', ['user_id' => $teacher->id, 'event_type' => 'consume']);

        $this->assertSoftDeleted('users', [
            'email' => 'aluno.lixeira@demo.avaliation.test',
            'organization_id' => $horizonte->id,
        ]);
        $this->assertSoftDeleted('school_classes', [
            'name' => 'Turma Encerrada — Lixeira',
            'organization_id' => $horizonte->id,
        ]);

        $crossTenantClassEnrollments = DB::table('class_student')
            ->join('school_classes', 'school_classes.id', '=', 'class_student.school_class_id')
            ->join('users', 'users.id', '=', 'class_student.user_id')
            ->whereColumn('school_classes.organization_id', '!=', 'users.organization_id')
            ->count();
        $crossTenantExamAssignments = DB::table('exam_school_class')
            ->join('exams', 'exams.id', '=', 'exam_school_class.exam_id')
            ->join('school_classes', 'school_classes.id', '=', 'exam_school_class.school_class_id')
            ->whereColumn('exams.organization_id', '!=', 'school_classes.organization_id')
            ->count();

        $this->assertSame(0, $crossTenantClassEnrollments);
        $this->assertSame(0, $crossTenantExamAssignments);
        $this->assertNotSame(
            User::where('email', 'student@email.com')->value('organization_id'),
            User::where('email', 'student01.aurora@demo.avaliation.test')->value('organization_id')
        );
    }
}
