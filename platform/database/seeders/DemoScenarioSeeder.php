<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\BNCcNode;
use App\Models\ClassOwnershipLog;
use App\Models\ConfigAuditLog;
use App\Models\ConfigRule;
use App\Models\CustomSkill;
use App\Models\Discipline;
use App\Models\EventLog;
use App\Models\Exam;
use App\Models\ExamAnswer;
use App\Models\ExamSubmission;
use App\Models\GuardianStudentLink;
use App\Models\InstitutionRole;
use App\Models\Invite;
use App\Models\KnowledgeArea;
use App\Models\LearningMaterial;
use App\Models\LearningMaterialProgress;
use App\Models\OmrAuditLog;
use App\Models\OmrCalibration;
use App\Models\OmrScan;
use App\Models\OmrScanPage;
use App\Models\OmrTemplate;
use App\Models\OmrTemplateVersion;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Question;
use App\Models\QuestionShare;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserOrganization;
use App\Services\ExamPrintService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Permission\Models\Role;

class DemoScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException(
                'Os cenários demonstrativos só podem ser gerados em ambiente local ou testing.'
            );
        }

        mt_srand(20260730);
        $this->writeDemoScanImage();

        DB::transaction(function (): void {
            $organization = Organization::where('subdomain', 'escola-modelo')->firstOrFail();
            $accounts = $this->seedPrimaryAccounts($organization);
            $classes = $this->seedPrimaryClasses($organization, $accounts);
            $questions = $this->seedCuratedQuestions($organization, $accounts);
            $exams = $this->seedPrimaryExams(
                $organization,
                $accounts['teacher'],
                $classes,
                $questions
            );

            $submissions = $this->seedPrimarySubmissions(
                $exams,
                $accounts['students']
            );

            $this->seedLearning(
                $organization,
                $accounts,
                $classes
            );
            $this->seedOmr(
                $organization,
                $accounts['teacher'],
                $classes['second_a'],
                $exams['paper'],
                $submissions['paper']
            );
            $this->seedAdministration(
                $organization,
                $accounts,
                $classes,
                $exams
            );
            $this->seedSecondaryTenant();
            $this->seedInactiveTenant();
            $this->seedTrashScenarios($organization, $accounts['admin']);
        }, 3);

        $this->printSummary();
    }

    /**
     * @return array{
     *   admin: User,
     *   teacher: User,
     *   coordinator: User,
     *   language_teacher: User,
     *   inactive_teacher: User,
     *   students: Collection<int, User>,
     *   inactive_students: Collection<int, User>,
     *   guardians: Collection<int, User>
     * }
     */
    private function seedPrimaryAccounts(Organization $organization): array
    {
        $admin = $this->user(
            $organization,
            'institution@email.com',
            'Marina Duarte (Gestora Demo)',
            'institution_admin'
        );
        $teacher = $this->user(
            $organization,
            'teacher@email.com',
            'Professor Rafael Silva',
            'teacher'
        );
        $coordinator = $this->user(
            $organization,
            'coordinator@email.com',
            'Coordenadora Helena Prado',
            'teacher'
        );
        $languageTeacher = $this->user(
            $organization,
            'teacher.portugues@email.com',
            'Professora Camila Nunes',
            'teacher'
        );
        $inactiveTeacher = $this->user(
            $organization,
            'teacher.inactive@email.com',
            'Professor Inativo Demo',
            'teacher',
            'inactive'
        );

        $studentNames = [
            'João Martins',
            'Ana Clara Souza',
            'Bruno Tavares',
            'Carolina Lima',
            'Daniel Rocha',
            'Eduarda Freitas',
            'Felipe Moraes',
            'Gabriela Costa',
            'Henrique Alves',
            'Isabela Ribeiro',
            'Lucas Barros',
            'Manuela Castro',
            'Nicolas Teixeira',
            'Olívia Campos',
            'Pedro Azevedo',
            'Rafaela Mendes',
            'Samuel Cardoso',
            'Vitória Pires',
            'Yasmin Correia',
            'Caio Monteiro',
        ];

        $students = collect();
        foreach ($studentNames as $index => $name) {
            $email = $index === 0
                ? 'student@email.com'
                : sprintf('aluno%02d@demo.avaliation.test', $index + 1);
            $student = $this->user(
                $organization,
                $email,
                $name,
                'student'
            );
            $student->studentProfile()->updateOrCreate(
                ['organization_id' => $organization->id],
                ['registration_number' => sprintf('HZ2026%04d', $index + 1)]
            );
            $students->push($student);
        }

        $inactiveStudents = collect([
            $this->user(
                $organization,
                'aluno.inativo@demo.avaliation.test',
                'Aluno Inativo Demo',
                'student',
                'inactive'
            ),
            $this->user(
                $organization,
                'aluno.verificacao@demo.avaliation.test',
                'Aluno Aguardando Verificação',
                'student',
                'active',
                false,
                ['requires_email_verification' => true]
            ),
        ]);

        foreach ($inactiveStudents as $index => $student) {
            $student->studentProfile()->updateOrCreate(
                ['organization_id' => $organization->id],
                ['registration_number' => sprintf('HZ-PEND-%02d', $index + 1)]
            );
        }

        $guardianData = [
            ['guardian@email.com', 'Responsável de João', 'Mãe'],
            ['responsavel02@demo.avaliation.test', 'Responsável de Ana e Bruno', 'Pai'],
            ['responsavel03@demo.avaliation.test', 'Responsável de Carolina', 'Avó'],
            ['responsavel04@demo.avaliation.test', 'Responsável de Pedro', 'Responsável legal'],
        ];
        $guardians = collect();
        foreach ($guardianData as [$email, $name]) {
            $guardians->push($this->user(
                $organization,
                $email,
                $name,
                'guardian'
            ));
        }

        $links = [
            [$guardians[0], $students[0], 'Mãe'],
            [$guardians[1], $students[1], 'Pai'],
            [$guardians[1], $students[2], 'Pai'],
            [$guardians[2], $students[3], 'Avó'],
            [$guardians[3], $students[14], 'Responsável legal'],
        ];
        foreach ($links as [$guardian, $student, $relationship]) {
            GuardianStudentLink::withTrashed()->updateOrCreate([
                'organization_id' => $organization->id,
                'guardian_id' => $guardian->id,
                'student_id' => $student->id,
            ], [
                'created_by' => $admin->id,
                'relationship' => $relationship,
                'deleted_at' => null,
            ]);
        }

        $removedLink = GuardianStudentLink::create([
            'organization_id' => $organization->id,
            'guardian_id' => $guardians[3]->id,
            'student_id' => $students[15]->id,
            'created_by' => $admin->id,
            'relationship' => 'Contato anterior',
        ]);
        $removedLink->delete();

        $coordinatorRole = InstitutionRole::updateOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'coordenador-pedagogico',
        ], [
            'name' => 'Coordenador Pedagógico',
            'description' => 'Acompanha turmas, resultados, relatórios e operação OMR.',
            'is_active' => true,
        ]);
        $coordinatorRole->syncPermissions([
            'view_teachers',
            'view_students',
            'view_classes',
            'view_questions',
            'view_exams',
            'view_reports',
            'view_omr',
            'manage_omr',
        ]);
        UserOrganization::where('user_id', $coordinator->id)
            ->where('organization_id', $organization->id)
            ->update(['institution_role_id' => $coordinatorRole->id]);

        $inactiveRole = InstitutionRole::updateOrCreate([
            'organization_id' => $organization->id,
            'slug' => 'estagiario-inativo',
        ], [
            'name' => 'Estagiário (inativo)',
            'description' => 'Cargo demonstrativo desativado.',
            'is_active' => false,
        ]);
        $inactiveRole->syncPermissions(['view_classes']);

        $organization->update([
            'owner_user_id' => $admin->id,
            'trash_access_users' => [$admin->id, $coordinator->id],
            'logs_access_users' => [$admin->id, $coordinator->id],
        ]);

        return compact(
            'admin',
            'teacher',
            'coordinator',
            'languageTeacher',
            'inactiveTeacher',
            'students',
            'inactiveStudents',
            'guardians'
        );
    }

    /**
     * @param  array<string, mixed>  $accounts
     * @return array<string, SchoolClass>
     */
    private function seedPrimaryClasses(
        Organization $organization,
        array $accounts
    ): array {
        $students = $accounts['students'];
        $classes = [
            'first_a' => $this->schoolClass($organization, '1º Ano A', '2026'),
            'first_b' => $this->schoolClass($organization, '1º Ano B', '2026'),
            'second_a' => $this->schoolClass($organization, '2º Ano A', '2026'),
            'sixth_a' => $this->schoolClass($organization, '6º Ano A', '2026'),
            'reinforcement' => $this->schoolClass($organization, 'Reforço de Matemática', '2026'),
        ];

        $classes['first_a']->students()->sync($students->slice(0, 10)->pluck('id')->all());
        $classes['first_b']->students()->sync($students->slice(10, 10)->pluck('id')->all());
        $classes['second_a']->students()->sync(
            $students->slice(4, 12)->pluck('id')->all()
        );
        $classes['sixth_a']->students()->sync(
            $students->slice(0, 16)->pluck('id')->all()
        );
        $classes['reinforcement']->students()->sync(
            $students->slice(0, 5)->pluck('id')->all()
        );

        $classes['first_a']->teachers()->sync([
            $accounts['teacher']->id => ['assigned_at' => now()->subMonths(6)],
            $accounts['languageTeacher']->id => ['assigned_at' => now()->subMonths(6)],
        ]);
        $classes['first_b']->teachers()->sync([
            $accounts['languageTeacher']->id => ['assigned_at' => now()->subMonths(5)],
            $accounts['coordinator']->id => ['assigned_at' => now()->subMonths(5)],
        ]);
        $classes['second_a']->teachers()->sync([
            $accounts['teacher']->id => ['assigned_at' => now()->subMonths(4)],
            $accounts['coordinator']->id => ['assigned_at' => now()->subMonths(4)],
        ]);
        $classes['sixth_a']->teachers()->sync([
            $accounts['teacher']->id => ['assigned_at' => now()->subMonths(3)],
            $accounts['languageTeacher']->id => ['assigned_at' => now()->subMonths(3)],
        ]);
        $classes['reinforcement']->teachers()->sync([
            $accounts['teacher']->id => ['assigned_at' => now()->subMonths(2)],
        ]);

        ClassOwnershipLog::create([
            'school_class_id' => $classes['second_a']->id,
            'previous_owner_type' => 'user',
            'previous_owner_id' => $accounts['teacher']->id,
            'new_owner_type' => 'organization',
            'new_owner_id' => $organization->id,
            'initiated_by' => $accounts['admin']->id,
            'transferred_at' => now()->subMonths(2),
        ]);

        return $classes;
    }

    /**
     * @param  array<string, mixed>  $accounts
     */
    private function seedCuratedQuestions(
        Organization $organization,
        array $accounts
    ): Collection {
        $teacher = $accounts['teacher'];
        $languageTeacher = $accounts['languageTeacher'];
        $disciplines = Discipline::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('name');

        $math = $disciplines->get('Matemática');
        $portuguese = $disciplines->get('Língua Portuguesa');
        $science = $disciplines->get('Ciências');
        $history = $disciplines->get('História');
        $geography = $disciplines->get('Geografia');

        $curated = collect([
            $this->question(
                $organization,
                $teacher,
                $math,
                'multiple_choice',
                [
                    'statement' => 'Uma turma arrecadou 48 livros e deseja distribuí-los igualmente entre 6 grupos. Quantos livros cada grupo receberá?',
                    'options' => ['6', '7', '8', '9'],
                    'correct_option' => 2,
                ],
                'easy',
                'ef_iniciais',
                '5'
            ),
            $this->question(
                $organization,
                $teacher,
                $math,
                'true_false',
                [
                    'statement' => 'A fração 3/4 é equivalente a 6/8.',
                    'options' => ['Verdadeiro', 'Falso'],
                    'correct_option' => 0,
                ],
                'medium',
                'ef_finais',
                '6'
            ),
            $this->question(
                $organization,
                $languageTeacher,
                $portuguese,
                'multiple_choice',
                [
                    'statement' => 'Na frase “Os estudantes organizaram a feira”, qual é o núcleo do sujeito?',
                    'options' => ['organizaram', 'feira', 'estudantes', 'a'],
                    'correct_option' => 2,
                ],
                'medium',
                'ef_finais',
                '6'
            ),
            $this->question(
                $organization,
                $languageTeacher,
                $portuguese,
                'essay',
                [
                    'statement' => 'Produza um parágrafo argumentativo sobre o uso responsável da água na escola.',
                    'rubric' => [
                        'title' => 'Texto argumentativo',
                        'description' => 'Correção por evidências observáveis.',
                        'criteria' => [
                            [
                                'title' => 'Tese',
                                'description' => 'Apresenta uma ideia central clara.',
                                'points' => 2,
                            ],
                            [
                                'title' => 'Argumentação',
                                'description' => 'Usa razões e exemplos coerentes.',
                                'points' => 3,
                            ],
                            [
                                'title' => 'Clareza e norma escrita',
                                'description' => 'Organiza as ideias com linguagem adequada.',
                                'points' => 2,
                            ],
                        ],
                    ],
                ],
                'hard',
                'ef_finais',
                '6'
            ),
            $this->question(
                $organization,
                $teacher,
                $science,
                'multiple_choice',
                [
                    'statement' => 'Qual ação contribui diretamente para reduzir a produção de resíduos?',
                    'options' => [
                        'Utilizar itens descartáveis diariamente',
                        'Reutilizar materiais sempre que possível',
                        'Misturar resíduos recicláveis e orgânicos',
                        'Descartar pilhas no lixo comum',
                    ],
                    'correct_option' => 1,
                ],
                'easy',
                'ef_finais',
                '7'
            ),
            $this->question(
                $organization,
                $teacher,
                $history,
                'multiple_choice',
                [
                    'statement' => 'Qual fonte pode ajudar no estudo da história de uma comunidade?',
                    'options' => [
                        'Somente livros didáticos',
                        'Fotografias, relatos e documentos',
                        'Apenas mapas atuais',
                        'Somente objetos novos',
                    ],
                    'correct_option' => 1,
                ],
                'medium',
                'ef_finais',
                '6'
            ),
            $this->question(
                $organization,
                $teacher,
                $geography,
                'true_false',
                [
                    'statement' => 'Mapas podem representar informações físicas, políticas e temáticas.',
                    'options' => ['Verdadeiro', 'Falso'],
                    'correct_option' => 0,
                ],
                'easy',
                'ef_finais',
                '6'
            ),
            $this->question(
                $organization,
                $teacher,
                $math,
                'multiple_choice',
                [
                    'statement' => 'Uma compra de R$ 240 recebeu desconto de 15%. Qual foi o valor final?',
                    'options' => ['R$ 204', 'R$ 210', 'R$ 216', 'R$ 225'],
                    'correct_option' => 0,
                ],
                'hard',
                'ef_finais',
                '8'
            ),
        ]);

        $mathSkill = CustomSkill::where('organization_id', $organization->id)
            ->where('name', 'Frações: Soma e Subtração')
            ->first();
        $bnccSkill = $math
            ? BNCcNode::where('discipline_id', $math->id)
                ->where('type', 'skill')
                ->first()
            : null;
        if ($mathSkill) {
            $curated[0]->customSkills()->sync([$mathSkill->id]);
            $curated[1]->customSkills()->sync([$mathSkill->id]);
        }
        if ($bnccSkill) {
            $curated[0]->bnccSkills()->sync([$bnccSkill->id]);
            $curated[1]->bnccSkills()->sync([$bnccSkill->id]);
        }

        QuestionShare::create([
            'question_id' => $curated[3]->id,
            'shared_with_user_id' => $accounts['coordinator']->id,
        ]);

        $duplicate = Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $teacher->id,
            'source_question_id' => $curated[2]->id,
            'type' => $curated[2]->type,
            'content' => $curated[2]->content,
            'visibility_scope' => 'private',
            'knowledge_area_id' => $curated[2]->knowledge_area_id,
            'discipline_id' => $curated[2]->discipline_id,
            'level' => $curated[2]->level,
            'stage' => $curated[2]->stage,
            'grade' => $curated[2]->grade,
        ]);
        $curated->push($duplicate);

        $bankQuestions = Question::where('organization_id', $organization->id)
            ->where('owner_id', $teacher->id)
            ->whereIn('type', ['multiple_choice', 'true_false'])
            ->whereNotIn('id', $curated->pluck('id')->all())
            ->limit(20)
            ->get();

        return $curated->merge($bankQuestions)->unique('id')->values();
    }

    /**
     * @param  array<string, SchoolClass>  $classes
     * @return array<string, Exam>
     */
    private function seedPrimaryExams(
        Organization $organization,
        User $teacher,
        array $classes,
        Collection $questions
    ): array {
        $objective = $questions
            ->whereIn('type', ['multiple_choice', 'true_false'])
            ->values();
        $essay = $questions->firstWhere('type', 'essay');

        $diagnostic = $this->exam(
            $organization,
            $teacher,
            'Diagnóstico de Matemática — 1º Ano A',
            'closed',
            'DIA1A1',
            [
                'application_mode' => 'online',
                'instructions' => 'Leia com atenção e registre seus cálculos.',
                'time_limit' => 50,
                'attempts' => 2,
                'available_from' => now()->subDays(90)->toIso8601String(),
                'available_until' => now()->subDays(15)->toIso8601String(),
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'show_score' => true,
                'show_answers' => true,
                'show_feedback' => true,
            ],
            $objective->take(10),
            collect([$classes['first_a']])
        );

        $active = $this->exam(
            $organization,
            $teacher,
            'Avaliação Bimestral — 2º Ano A',
            'published',
            'BIM2A1',
            [
                'application_mode' => 'online',
                'instructions' => 'A atividade salva automaticamente.',
                'time_limit' => 60,
                'attempts' => 1,
                'available_from' => now()->subDay()->toIso8601String(),
                'available_until' => now()->addDays(7)->toIso8601String(),
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'show_score' => false,
                'show_answers' => false,
                'show_feedback' => false,
                'results_available_from' => now()->addDays(8)->toIso8601String(),
            ],
            $objective->slice(4, 8),
            collect([$classes['second_a']])
        );

        $essayExam = $this->exam(
            $organization,
            $teacher,
            'Produção Textual e Sustentabilidade',
            'closed',
            'TEX6A1',
            [
                'application_mode' => 'hybrid',
                'instructions' => 'Responda à questão discursiva com argumentos.',
                'time_limit' => 80,
                'attempts' => 1,
                'available_from' => now()->subDays(45)->toIso8601String(),
                'available_until' => now()->subDays(5)->toIso8601String(),
                'show_score' => true,
                'show_answers' => false,
                'show_feedback' => true,
            ],
            collect([$questions[2], $essay, $questions[4]])->filter(),
            collect([$classes['sixth_a']])
        );

        $paper = $this->exam(
            $organization,
            $teacher,
            'Simulado Impresso Multidisciplinar',
            'published',
            'PAP2A1',
            [
                'application_mode' => 'paper',
                'instructions' => 'Preencha o cartão com caneta azul ou preta.',
                'attempts' => 1,
                'available_from' => now()->subDays(30)->toIso8601String(),
                'available_until' => now()->addDays(30)->toIso8601String(),
                'show_score' => true,
                'show_answers' => true,
                'show_feedback' => true,
                'shuffle_questions' => true,
                'shuffle_options' => true,
            ],
            $objective->take(12),
            collect([$classes['second_a']])
        );

        $future = $this->exam(
            $organization,
            $teacher,
            'Revisão Agendada — 1º Ano B',
            'published',
            'FUT1B1',
            [
                'application_mode' => 'hybrid',
                'instructions' => 'Avaliação futura para testar o calendário de disponibilidade.',
                'time_limit' => 40,
                'attempts' => 1,
                'available_from' => now()->addDays(10)->toIso8601String(),
                'available_until' => now()->addDays(12)->toIso8601String(),
                'show_score' => true,
                'show_answers' => false,
                'show_feedback' => true,
            ],
            $objective->slice(2, 6),
            collect([$classes['first_b']])
        );

        $draft = Exam::create([
            'organization_id' => $organization->id,
            'author_id' => $teacher->id,
            'title' => 'Rascunho sem questões — validação controlada',
            'status' => 'draft',
            'settings' => [
                'application_mode' => 'online',
                'time_limit' => 30,
                'attempts' => 1,
            ],
        ]);

        $archived = $this->exam(
            $organization,
            $teacher,
            'Avaliação Arquivada — Lixeira',
            'closed',
            null,
            ['application_mode' => 'online'],
            $objective->take(3),
            collect([$classes['first_a']])
        );
        $archived->delete();

        return compact(
            'diagnostic',
            'active',
            'essayExam',
            'paper',
            'future',
            'draft',
            'archived'
        );
    }

    /**
     * @param  array<string, Exam>  $exams
     * @return array{paper: ExamSubmission}
     */
    private function seedPrimarySubmissions(
        array $exams,
        Collection $students
    ): array {
        $performances = [42, 55, 61, 68, 74, 81, 88, 93, 100, 36, 66, 79];
        foreach ($performances as $index => $percentage) {
            $this->gradedSubmission(
                $exams['diagnostic'],
                $students[$index],
                $percentage,
                now()->subDays(75 - ($index * 4))
            );
        }
        $this->gradedSubmission(
            $exams['diagnostic'],
            $students[0],
            68,
            now()->subDays(8),
            2
        );

        $this->inProgressSubmission($exams['active'], $students[0], 1, 4);
        $this->inProgressSubmission($exams['active'], $students[1], 1, 2);
        foreach ([2 => 50, 3 => 75, 4 => 88, 5 => 100] as $index => $percentage) {
            $this->gradedSubmission(
                $exams['active'],
                $students[$index],
                $percentage,
                now()->subHours(12 - $index)
            );
        }
        $this->submittedSubmission($exams['active'], $students[6]);

        foreach ([0 => 48, 1 => 58, 2 => 72, 3 => 84, 4 => 92, 5 => 100] as $index => $percentage) {
            $this->gradedSubmission(
                $exams['essayExam'],
                $students[$index],
                $percentage,
                now()->subDays(20 - ($index * 2))
            );
        }
        $this->submittedSubmission($exams['essayExam'], $students[7]);

        $paperSubmission = null;
        foreach ([5 => 60, 6 => 70, 7 => 80, 8 => 90, 9 => 100] as $index => $percentage) {
            $submission = $this->gradedSubmission(
                $exams['paper'],
                $students[$index],
                $percentage,
                now()->subDays(3)
            );
            $paperSubmission ??= $submission;
        }

        return ['paper' => $paperSubmission];
    }

    /**
     * @param  array<string, mixed>  $accounts
     * @param  array<string, SchoolClass>  $classes
     */
    private function seedLearning(
        Organization $organization,
        array $accounts,
        array $classes
    ): void {
        $disciplines = Discipline::withoutGlobalScopes()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('name');
        $materials = collect([
            $this->material(
                $organization,
                $accounts['teacher'],
                'Revisão guiada: frações equivalentes',
                'Resumo, exemplos resolvidos e exercícios para revisar frações.',
                'Compare numeradores e denominadores, simplifique as frações e resolva os exemplos propostos.',
                $disciplines->get('Matemática'),
                'published',
                collect([$classes['first_a'], $classes['reinforcement']])
            ),
            $this->material(
                $organization,
                $accounts['languageTeacher'],
                'Como construir um parágrafo argumentativo',
                'Roteiro de tese, argumentos, exemplos e conclusão.',
                'Comece apresentando a ideia central. Em seguida, use ao menos uma evidência e explique sua relação com a tese.',
                $disciplines->get('Língua Portuguesa'),
                'published',
                collect([$classes['sixth_a']])
            ),
            $this->material(
                $organization,
                $accounts['teacher'],
                'Consumo consciente e sustentabilidade',
                'Conteúdo de Ciências relacionado aos erros mais frequentes.',
                'Analise hábitos da escola e proponha três ações possíveis para reduzir resíduos.',
                $disciplines->get('Ciências'),
                'published',
                collect([$classes['second_a'], $classes['sixth_a']])
            ),
            $this->material(
                $organization,
                $accounts['teacher'],
                'Leitura e interpretação de mapas',
                'Escala, legenda, orientação e mapas temáticos.',
                'Observe os elementos do mapa e explique como a legenda comunica informações.',
                $disciplines->get('Geografia'),
                'published',
                collect([$classes['sixth_a']])
            ),
            $this->material(
                $organization,
                $accounts['coordinator'],
                'Plano de estudos da recuperação',
                'Material em preparação, ainda não visível aos alunos.',
                'Conteúdo preliminar para revisão da coordenação.',
                $disciplines->get('Matemática'),
                'draft',
                collect([$classes['reinforcement']])
            ),
        ]);

        $archived = $this->material(
            $organization,
            $accounts['teacher'],
            'Material antigo — lixeira',
            'Exemplo de conteúdo removido.',
            'Este conteúdo está arquivado.',
            $disciplines->get('História'),
            'draft',
            collect([$classes['first_a']])
        );
        $archived->delete();

        foreach ($accounts['students']->take(12) as $studentIndex => $student) {
            foreach ($materials->take(4) as $materialIndex => $material) {
                if (($studentIndex + $materialIndex) % 3 === 0) {
                    continue;
                }

                $completed = ($studentIndex + $materialIndex) % 2 === 0;
                LearningMaterialProgress::updateOrCreate([
                    'organization_id' => $organization->id,
                    'learning_material_id' => $material->id,
                    'student_id' => $student->id,
                ], [
                    'status' => $completed ? 'completed' : 'opened',
                    'view_count' => 1 + (($studentIndex + $materialIndex) % 4),
                    'opened_at' => now()->subDays(12 - $studentIndex),
                    'last_viewed_at' => now()->subDays($studentIndex % 4),
                    'completed_at' => $completed
                        ? now()->subDays($studentIndex % 3)
                        : null,
                ]);
            }
        }
    }

    private function seedOmr(
        Organization $organization,
        User $teacher,
        SchoolClass $schoolClass,
        Exam $exam,
        ExamSubmission $paperSubmission
    ): void {
        $systemTemplate = OmrTemplate::where('slug', 'sistema-padrao')->firstOrFail();
        $template = OmrTemplate::create([
            'name' => 'Cartão Escola Horizonte — 40 questões',
            'slug' => 'horizonte-40',
            'organization_id' => $organization->id,
            'created_by' => $teacher->id,
            'owner_type' => User::class,
            'owner_id' => $teacher->id,
            'visibility_scope' => 'org_public',
            'is_default' => false,
            'is_system' => false,
            'width' => 2480,
            'height' => 3508,
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'corner_points_json' => $systemTemplate->corner_points_json,
            'thresholds_json' => $systemTemplate->thresholds_json,
            'layout_config' => $systemTemplate->layout_config,
            'header_config' => [
                'title' => 'SIMULADO ESCOLA HORIZONTE',
                'show_institution' => true,
                'show_qr' => true,
            ],
            'total_questions' => 40,
            'max_questions' => 40,
            'max_columns' => 2,
            'total_pages' => 1,
            'columns' => 2,
            'rows_per_column' => 20,
            'max_options' => 5,
            'current_version' => 1,
            'is_active' => true,
        ]);
        OmrTemplateVersion::create([
            'omr_template_id' => $template->id,
            'version' => 1,
            'layout_config' => $template->layout_config,
            'header_config' => $template->header_config,
        ]);
        $exam->update([
            'card_template_id' => $template->id,
            'card_template_version' => 1,
        ]);

        $copies = app(ExamPrintService::class)->generateCopies(
            $exam,
            6,
            [
                'shuffle_questions' => true,
                'shuffle_options_mc' => true,
                'shuffle_options_tf' => true,
                'group_disciplines' => true,
            ],
            $schoolClass->id
        );

        $answers = $exam->questions->mapWithKeys(
            fn (Question $question): array => [
                $question->id => (int) ($question->content['correct_option'] ?? 0),
            ]
        )->all();
        $totalPoints = (float) $exam->questions->sum('pivot.points');

        $scenarios = [
            ['confirmed', 0.98, false, $paperSubmission],
            ['reviewing', 0.62, true, null],
            ['pending', 0.00, true, null],
            ['rejected', 0.31, true, null],
            ['synced', 0.91, false, null],
        ];

        foreach ($scenarios as $index => [$status, $confidence, $needsReview, $submission]) {
            $copy = $copies[$index];
            $student = $schoolClass->students()->orderBy('users.id')->skip($index)->first();
            $sessionId = 'demo-omr-'.$status.'-'.$exam->id;
            $scan = OmrScan::create([
                'exam_id' => $exam->id,
                'copy_id' => $copy->id,
                'omr_template_id' => $template->id,
                'organization_id' => $organization->id,
                'uploaded_by' => $teacher->id,
                'image_path' => 'omr/demo/cartao-exemplo.svg',
                'idempotency_key' => 'demo-'.$status.'-'.$exam->id,
                'status' => $status,
                'detected_answers' => $status === 'pending' ? [] : $answers,
                'confirmed_answers' => $status === 'confirmed' ? $answers : null,
                'student_id' => $student?->id,
                'exam_submission_id' => $submission?->id,
                'confidence_score' => $confidence,
                'notes' => $needsReview
                    ? 'Cenário fictício com marcação ambígua para revisão.'
                    : 'Leitura fictícia com boa qualidade.',
                'source' => $index % 2 === 0 ? 'mobile' : 'web',
                'session_id' => $sessionId,
                'layout_version' => 1,
                'total_pages' => 1,
                'raw_answers' => $answers,
                'raw_confidences' => array_fill_keys(array_keys($answers), $confidence),
                'overall_confidence' => $confidence,
                'quality_json' => [
                    'needs_review' => $needsReview,
                    'blur_score' => $needsReview ? 0.44 : 0.91,
                    'brightness' => $needsReview ? 0.58 : 0.78,
                    'perspective_ok' => ! $needsReview,
                ],
                'score' => $status === 'confirmed' ? $paperSubmission->score : null,
                'total_points' => $totalPoints,
                'grading_details' => [
                    'objective_questions' => count($answers),
                    'source' => 'demo',
                ],
                'layout_meta' => [
                    'template_version' => 1,
                    'paper_size' => 'A4',
                ],
            ]);

            OmrScanPage::create([
                'organization_id' => $organization->id,
                'session_id' => $sessionId,
                'exam_id' => $exam->id,
                'copy_id' => $copy->id,
                'student_id' => $student?->id,
                'uploaded_by' => $teacher->id,
                'page_index' => 1,
                'total_pages' => 1,
                'image_path' => 'omr/demo/cartao-exemplo.svg',
                'raw_answers' => $answers,
                'raw_confidences' => array_fill_keys(array_keys($answers), $confidence),
                'overall_confidence' => $confidence,
                'status' => in_array($status, ['confirmed', 'synced'], true)
                    ? 'consolidated'
                    : $status,
            ]);

            OmrAuditLog::create([
                'omr_scan_id' => $scan->id,
                'user_id' => $teacher->id,
                'action' => $status === 'confirmed' ? 'confirmed' : 'created',
                'previous_data' => null,
                'new_data' => [
                    'status' => $status,
                    'confidence' => $confidence,
                    'demo' => true,
                ],
            ]);
        }

        OmrCalibration::create([
            'exam_id' => $exam->id,
            'offset_x' => 1.2,
            'offset_y' => -0.8,
            'scale_x' => 1.01,
            'scale_y' => 0.99,
            'rotation_deg' => 0.35,
        ]);
    }

    /**
     * @param  array<string, mixed>  $accounts
     * @param  array<string, SchoolClass>  $classes
     * @param  array<string, Exam>  $exams
     */
    private function seedAdministration(
        Organization $organization,
        array $accounts,
        array $classes,
        array $exams
    ): void {
        $admin = $accounts['admin'];
        $pendingInvite = Invite::create([
            'inviter_id' => $admin->id,
            'organization_id' => $organization->id,
            'invitee_email' => 'novo.professor@demo.avaliation.test',
            'target_role' => 'teacher',
            'invite_type' => 'org_teacher',
            'status' => 'pending',
            'expires_at' => now()->addDays(5),
        ]);
        Invite::create([
            'inviter_id' => $admin->id,
            'organization_id' => $organization->id,
            'invitee_email' => $accounts['coordinator']->email,
            'invitee_user_id' => $accounts['coordinator']->id,
            'target_role' => 'teacher',
            'invite_type' => 'org_teacher',
            'status' => 'accepted',
            'expires_at' => now()->addMonth(),
        ]);
        Invite::create([
            'inviter_id' => $admin->id,
            'organization_id' => $organization->id,
            'invitee_email' => 'convite.expirado@demo.avaliation.test',
            'target_role' => 'student',
            'invite_type' => 'class_enrollment',
            'target_entity_type' => SchoolClass::class,
            'target_entity_id' => $classes['first_b']->id,
            'status' => 'pending',
            'expires_at' => now()->subDays(2),
        ]);
        Invite::create([
            'inviter_id' => $admin->id,
            'organization_id' => $organization->id,
            'invitee_email' => 'convite.recusado@demo.avaliation.test',
            'target_role' => 'student',
            'invite_type' => 'class_enrollment',
            'target_entity_type' => SchoolClass::class,
            'target_entity_id' => $classes['second_a']->id,
            'status' => 'declined',
            'expires_at' => now()->addDays(3),
        ]);

        $globalConfig = ConfigRule::create([
            'organization_id' => $organization->id,
            'config_key' => 'answer_sheet_type',
            'config_value' => 'essential',
            'scope_type' => 'global',
            'scope_id' => null,
            'priority' => 5,
            'is_active' => true,
            'effective_from' => now()->subMonths(6),
            'created_by' => $admin->id,
        ]);
        $userConfig = ConfigRule::create([
            'organization_id' => $organization->id,
            'config_key' => 'scan_mode',
            'config_value' => 'preloaded',
            'scope_type' => 'user',
            'scope_id' => (string) $accounts['teacher']->id,
            'priority' => 1,
            'is_active' => true,
            'effective_from' => now()->subMonth(),
            'effective_until' => now()->addMonths(2),
            'created_by' => $admin->id,
        ]);
        ConfigRule::create([
            'organization_id' => $organization->id,
            'config_key' => 'scan_mode',
            'config_value' => 'qr_embedded',
            'scope_type' => 'role',
            'scope_id' => 'teacher',
            'priority' => 3,
            'is_active' => false,
            'effective_from' => now()->subYear(),
            'effective_until' => now()->subMonths(6),
            'created_by' => $admin->id,
        ]);
        ConfigAuditLog::create([
            'organization_id' => $organization->id,
            'config_rule_id' => $globalConfig->id,
            'action' => 'created',
            'config_key' => 'answer_sheet_type',
            'old_value' => null,
            'new_value' => [
                'value' => 'essential',
                'scope_type' => 'global',
            ],
            'changed_by' => $admin->id,
            'change_reason' => 'Padronização do cartão para a demonstração.',
            'created_at' => now()->subMonths(6),
        ]);
        ConfigAuditLog::create([
            'organization_id' => $organization->id,
            'config_rule_id' => $userConfig->id,
            'action' => 'updated',
            'config_key' => 'scan_mode',
            'old_value' => ['value' => 'hybrid'],
            'new_value' => [
                'value' => 'preloaded',
                'scope_type' => 'user',
            ],
            'changed_by' => $admin->id,
            'change_reason' => 'Teste temporário no dispositivo do professor.',
            'created_at' => now()->subMonth(),
        ]);

        $pro = Plan::where('slug', 'pro')->firstOrFail();
        Subscription::create([
            'organization_id' => $organization->id,
            'plan_id' => $pro->id,
            'status' => 'canceled',
            'starts_at' => now()->subYears(2),
            'expires_at' => now()->subYear(),
        ]);

        $events = [
            [
                'billing.payment_succeeded',
                'info',
                'Pagamento fictício da assinatura confirmado.',
                ['amount' => 39.90, 'currency' => 'BRL', 'reference' => 'DEMO-PAY-001'],
                now()->subDays(20),
            ],
            [
                'billing.courtesy_granted',
                'info',
                'Cortesia fictícia de 30 dias registrada para demonstração.',
                [
                    'benefit_type' => 'courtesy_days',
                    'quantity' => 30,
                    'starts_at' => now()->toDateString(),
                    'ends_at' => now()->addDays(30)->toDateString(),
                    'reason' => 'Piloto pedagógico demonstrativo',
                    'authorized_by' => 'Administrador Global Demo',
                ],
                now()->subDays(5),
            ],
            [
                'students.import_completed',
                'info',
                'Importação fictícia de 20 alunos concluída.',
                ['rows' => 20, 'invalid_rows' => 2, 'file' => 'alunos-demo.csv'],
                now()->subDays(12),
            ],
            [
                'exam.exported_pdf',
                'info',
                'Prova e cartões-resposta exportados em PDF.',
                ['exam_id' => $exams['paper']->id, 'copies' => 6],
                now()->subDays(3),
            ],
            [
                'omr.low_confidence',
                'warning',
                'Leitura OMR encaminhada para revisão por baixa confiança.',
                ['exam_id' => $exams['paper']->id, 'confidence' => 0.62],
                now()->subDays(2),
            ],
            [
                'auth.access_denied',
                'critical',
                'Tentativa controlada de acesso entre instituições bloqueada.',
                ['reason' => 'tenant_mismatch'],
                now()->subDay(),
            ],
        ];
        foreach ($events as [$code, $severity, $message, $context, $date]) {
            $event = EventLog::create([
                'organization_id' => $organization->id,
                'actor_user_id' => $admin->id,
                'event_code' => $code,
                'severity' => $severity,
                'entity_type' => Organization::class,
                'entity_id' => $organization->id,
                'message' => $message,
                'context_json' => $context,
                'ip' => '192.0.2.10',
                'user_agent' => 'Avaliation Demo Seeder',
            ]);
            $event->forceFill([
                'created_at' => $date,
                'updated_at' => $date,
            ])->saveQuietly();
        }

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'invite_sent',
            'model_type' => Invite::class,
            'model_id' => $pendingInvite->id,
            'payload' => [
                'email' => $pendingInvite->invitee_email,
                'role' => 'teacher',
                'demo' => true,
            ],
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Avaliation Demo Seeder',
            'created_at' => now()->subHours(4),
        ]);
        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'plan_changed',
            'model_type' => Organization::class,
            'model_id' => $organization->id,
            'payload' => [
                'old_plan' => 'Start',
                'new_plan' => 'Profissional',
                'reason' => 'Cenário demonstrativo',
            ],
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Avaliation Demo Seeder',
            'created_at' => now()->subMonths(8),
        ]);
    }

    private function seedSecondaryTenant(): void
    {
        $organization = Organization::updateOrCreate([
            'subdomain' => 'colegio-aurora',
        ], [
            'name' => 'Colégio Aurora — Tenant Isolado',
            'active' => true,
            'allow_class_copy' => false,
            'can_access_trash' => true,
            'can_access_logs' => false,
            'settings' => ['demo' => true, 'school_year' => 2026],
        ]);
        $enterprise = Plan::where('slug', 'enterprise')->firstOrFail();
        Subscription::updateOrCreate([
            'organization_id' => $organization->id,
            'status' => 'active',
        ], [
            'plan_id' => $enterprise->id,
            'starts_at' => now()->subMonths(4),
            'expires_at' => now()->addMonths(8),
        ]);

        $admin = $this->user(
            $organization,
            'institution.aurora@email.com',
            'Gestor do Colégio Aurora',
            'institution_admin'
        );
        $teacher = $this->user(
            $organization,
            'teacher.aurora@email.com',
            'Professora Aurora Demo',
            'teacher'
        );
        $students = collect();
        foreach (range(1, 8) as $number) {
            $student = $this->user(
                $organization,
                sprintf('student%02d.aurora@demo.avaliation.test', $number),
                sprintf('Estudante Aurora %02d', $number),
                'student'
            );
            $student->studentProfile()->create([
                'organization_id' => $organization->id,
                'registration_number' => sprintf('AU2026%03d', $number),
            ]);
            $students->push($student);
        }
        $guardian = $this->user(
            $organization,
            'guardian.aurora@email.com',
            'Responsável Aurora Demo',
            'guardian'
        );
        GuardianStudentLink::create([
            'organization_id' => $organization->id,
            'guardian_id' => $guardian->id,
            'student_id' => $students[0]->id,
            'created_by' => $admin->id,
            'relationship' => 'Responsável legal',
        ]);
        $organization->update(['owner_user_id' => $admin->id]);

        $area = KnowledgeArea::create([
            'organization_id' => $organization->id,
            'name' => 'Matemática',
        ]);
        $discipline = Discipline::create([
            'organization_id' => $organization->id,
            'name' => 'Matemática',
        ]);
        $discipline->forceFill(['knowledge_area_id' => $area->id])->saveQuietly();
        $unit = BNCcNode::create([
            'discipline_id' => $discipline->id,
            'stage' => 'ef_iniciais',
            'type' => 'thematic_unit',
            'title' => 'Números',
            'is_active' => true,
        ]);
        $skill = BNCcNode::create([
            'discipline_id' => $discipline->id,
            'stage' => 'ef_iniciais',
            'grade' => '2',
            'type' => 'skill',
            'code' => 'EF02MA-DEMO',
            'title' => 'Resolver problemas de adição e subtração em contexto.',
            'parent_id' => $unit->id,
            'is_active' => true,
        ]);
        $customSkill = CustomSkill::create([
            'organization_id' => $organization->id,
            'name' => 'Cálculo mental — Aurora',
        ]);

        $questions = collect();
        foreach (range(1, 6) as $number) {
            $question = Question::create([
                'organization_id' => $organization->id,
                'owner_id' => $teacher->id,
                'type' => 'multiple_choice',
                'content' => [
                    'statement' => "Questão Aurora {$number}: quanto é {$number} + ".($number + 2).'? ',
                    'options' => [
                        (string) ($number + 1),
                        (string) ($number + 2),
                        (string) ($number * 2 + 2),
                        (string) ($number + 4),
                    ],
                    'correct_option' => 2,
                ],
                'visibility_scope' => 'org_public',
                'knowledge_area_id' => $area->id,
                'discipline_id' => $discipline->id,
                'level' => $number <= 3 ? 'easy' : 'medium',
                'stage' => 'ef_iniciais',
                'grade' => '2',
            ]);
            $question->bnccSkills()->sync([$skill->id]);
            $question->customSkills()->sync([$customSkill->id]);
            $questions->push($question);
        }

        $class = $this->schoolClass($organization, '2º Ano A — Aurora', '2026');
        $class->students()->sync($students->pluck('id')->all());
        $class->teachers()->sync([
            $teacher->id => ['assigned_at' => now()->subMonths(4)],
        ]);
        $exam = $this->exam(
            $organization,
            $teacher,
            'Avaliação Aurora — Operações',
            'published',
            'AUR2A1',
            [
                'application_mode' => 'online',
                'time_limit' => 40,
                'attempts' => 1,
                'available_from' => now()->subMonth()->toIso8601String(),
                'available_until' => now()->addMonth()->toIso8601String(),
                'show_score' => true,
                'show_answers' => true,
                'show_feedback' => true,
            ],
            $questions,
            collect([$class])
        );
        foreach ([45, 60, 75, 90] as $index => $performance) {
            $this->gradedSubmission(
                $exam,
                $students[$index],
                $performance,
                now()->subDays(4 - $index)
            );
        }
        $material = $this->material(
            $organization,
            $teacher,
            'Revisão Aurora: adição e subtração',
            'Material exclusivo do tenant Aurora.',
            'Resolva os exemplos e confira as estratégias de cálculo mental.',
            $discipline,
            'published',
            collect([$class])
        );
        LearningMaterialProgress::create([
            'organization_id' => $organization->id,
            'learning_material_id' => $material->id,
            'student_id' => $students[0]->id,
            'status' => 'completed',
            'view_count' => 2,
            'opened_at' => now()->subDays(3),
            'last_viewed_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);
        EventLog::create([
            'organization_id' => $organization->id,
            'actor_user_id' => $admin->id,
            'event_code' => 'tenant.demo_ready',
            'severity' => 'info',
            'message' => 'Tenant Aurora preparado para teste de isolamento.',
            'context_json' => ['students' => 8, 'classes' => 1, 'exams' => 1],
        ]);
    }

    private function seedInactiveTenant(): void
    {
        $organization = Organization::updateOrCreate([
            'subdomain' => 'instituicao-inativa',
        ], [
            'name' => 'Instituição Suspensa — Cenário Demo',
            'active' => false,
            'settings' => ['demo' => true, 'suspension_reason' => 'billing_past_due'],
        ]);
        $start = Plan::where('slug', 'start')->firstOrFail();
        Subscription::updateOrCreate([
            'organization_id' => $organization->id,
            'status' => 'past_due',
        ], [
            'plan_id' => $start->id,
            'starts_at' => now()->subMonths(3),
            'expires_at' => now()->subDays(10),
        ]);
        $admin = $this->user(
            $organization,
            'institution.inactive@email.com',
            'Gestor de Instituição Suspensa',
            'institution_admin'
        );
        $this->user(
            $organization,
            'teacher.suspended@email.com',
            'Professor com Conta Inativa',
            'teacher',
            'inactive'
        );
        $organization->update(['owner_user_id' => $admin->id]);
        EventLog::create([
            'organization_id' => $organization->id,
            'actor_user_id' => null,
            'event_code' => 'billing.payment_pending',
            'severity' => 'critical',
            'message' => 'Pagamento fictício pendente; acesso institucional suspenso.',
            'context_json' => [
                'amount' => 49.90,
                'due_at' => now()->subDays(10)->toDateString(),
                'status' => 'past_due',
            ],
        ]);
    }

    private function seedTrashScenarios(
        Organization $organization,
        User $admin
    ): void {
        $student = $this->user(
            $organization,
            'aluno.lixeira@demo.avaliation.test',
            'Aluno Removido — Lixeira',
            'student'
        );
        $student->studentProfile()->create([
            'organization_id' => $organization->id,
            'registration_number' => 'HZ-TRASH-01',
        ]);
        $student->delete();

        $class = $this->schoolClass(
            $organization,
            'Turma Encerrada — Lixeira',
            '2025'
        );
        $class->delete();

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'deleted',
            'model_type' => User::class,
            'model_id' => $student->id,
            'payload' => [
                'reason' => 'Cenário controlado para testar restauração.',
                'demo' => true,
            ],
            'ip_address' => '192.0.2.10',
            'user_agent' => 'Avaliation Demo Seeder',
            'created_at' => now()->subDay(),
        ]);
    }

    private function user(
        Organization $organization,
        string $email,
        string $name,
        string $type,
        string $status = 'active',
        bool $verified = true,
        array $settings = []
    ): User {
        $user = User::updateOrCreate(['email' => $email], [
            'organization_id' => $organization->id,
            'name' => $name,
            'password' => 'password',
            'type' => $type,
            'status' => $status,
            'settings' => array_merge(['demo' => true], $settings),
        ]);
        $user->forceFill([
            'email_verified_at' => $verified ? now() : null,
        ])->saveQuietly();

        Role::findOrCreate($type, 'web');
        $user->syncRoles([$type]);
        $user->organizations()->syncWithoutDetaching([
            $organization->id => [
                'role_in_org' => match ($type) {
                    'institution_admin' => 'admin',
                    default => $type,
                },
                'status' => $status,
                'joined_at' => now()->subMonths(6),
            ],
        ]);

        return $user;
    }

    private function schoolClass(
        Organization $organization,
        string $name,
        string $year
    ): SchoolClass {
        return SchoolClass::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'owner_type' => 'organization',
            'owner_id' => $organization->id,
            'name' => $name,
            'year' => $year,
        ]);
    }

    private function question(
        Organization $organization,
        User $owner,
        ?Discipline $discipline,
        string $type,
        array $content,
        string $level,
        string $stage,
        string $grade
    ): Question {
        return Question::create([
            'organization_id' => $organization->id,
            'owner_id' => $owner->id,
            'type' => $type,
            'content' => $content,
            'visibility_scope' => 'org_public',
            'knowledge_area_id' => $discipline?->knowledge_area_id,
            'discipline_id' => $discipline?->id,
            'level' => $level,
            'stage' => $stage,
            'grade' => $grade,
        ]);
    }

    private function exam(
        Organization $organization,
        User $author,
        string $title,
        string $status,
        ?string $accessCode,
        array $settings,
        Collection $questions,
        Collection $classes
    ): Exam {
        $exam = Exam::withoutGlobalScopes()->create([
            'organization_id' => $organization->id,
            'author_id' => $author->id,
            'title' => $title,
            'status' => $status,
            'access_code' => $accessCode,
            'settings' => $settings,
            'answer_sheet_type_slug' => 'essential',
            'version' => 1,
        ]);
        foreach ($questions->values() as $index => $question) {
            $exam->questions()->attach($question->id, [
                'points' => $question->type === 'essay' ? 7 : 1,
                'order' => $index + 1,
            ]);
        }
        $exam->schoolClasses()->sync($classes->pluck('id')->all());

        return $exam->fresh(['questions', 'schoolClasses']);
    }

    private function gradedSubmission(
        Exam $exam,
        User $student,
        int $percentage,
        $finishedAt,
        int $attempt = 1
    ): ExamSubmission {
        $submission = ExamSubmission::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'attempt_number' => $attempt,
            'status' => 'graded',
            'started_at' => $finishedAt->copy()->subMinutes(45),
            'deadline_at' => $finishedAt->copy()->addMinutes(15),
            'client_token' => (string) Str::uuid(),
            'finished_at' => $finishedAt,
            'score' => 0,
            'feedback' => $percentage >= 80
                ? 'Ótimo trabalho. Continue aprofundando as estratégias usadas.'
                : ($percentage >= 60
                    ? 'Bom progresso. Revise os itens indicados.'
                    : 'Reveja os materiais recomendados e tente novamente.'),
        ]);

        $objectiveQuestions = $exam->questions
            ->whereIn('type', ['multiple_choice', 'true_false'])
            ->values();
        $correctTarget = (int) round(
            $objectiveQuestions->count() * ($percentage / 100)
        );
        $score = 0.0;
        $objectiveIndex = 0;

        foreach ($exam->questions as $question) {
            $points = (float) $question->pivot->points;
            if ($question->type === 'essay') {
                $awarded = round($points * ($percentage / 100), 2);
                $score += $awarded;
                $answer = ExamAnswer::create([
                    'exam_submission_id' => $submission->id,
                    'question_id' => $question->id,
                    'answer_data' => [
                        'raw' => 'A escola pode reduzir o consumo de água com manutenção preventiva, campanhas e acompanhamento dos resultados.',
                    ],
                    'is_correct' => null,
                    'points_awarded' => $awarded,
                    'feedback' => 'Resposta coerente; amplie as evidências e detalhe os impactos.',
                    'grading_justification' => 'Pontuação aplicada conforme a rubrica demonstrativa.',
                    'rubric_scores' => [
                        ['criterion' => 'Tese', 'awarded' => round(2 * $percentage / 100, 2), 'max' => 2],
                        ['criterion' => 'Argumentação', 'awarded' => round(3 * $percentage / 100, 2), 'max' => 3],
                        ['criterion' => 'Clareza', 'awarded' => round(2 * $percentage / 100, 2), 'max' => 2],
                    ],
                ]);
            } else {
                $correct = $objectiveIndex < $correctTarget;
                $correctOption = (int) ($question->content['correct_option'] ?? 0);
                $optionCount = max(2, count($question->content['options'] ?? []));
                $selected = $correct
                    ? $correctOption
                    : ($correctOption + 1) % $optionCount;
                $awarded = $correct ? $points : 0;
                $score += $awarded;
                $answer = ExamAnswer::create([
                    'exam_submission_id' => $submission->id,
                    'question_id' => $question->id,
                    'answer_data' => ['raw' => $selected],
                    'is_correct' => $correct,
                    'points_awarded' => $awarded,
                    'feedback' => $correct
                        ? 'Resposta correta.'
                        : 'Revise o conceito relacionado a esta questão.',
                    'grading_justification' => 'Correção automática pelo gabarito.',
                ]);
                $objectiveIndex++;
            }
            $answer->forceFill([
                'created_at' => $finishedAt,
                'updated_at' => $finishedAt,
            ])->saveQuietly();
        }

        $submission->forceFill([
            'score' => round($score, 2),
            'created_at' => $finishedAt->copy()->subMinutes(45),
            'updated_at' => $finishedAt,
        ])->saveQuietly();

        return $submission->fresh();
    }

    private function inProgressSubmission(
        Exam $exam,
        User $student,
        int $attempt,
        int $answered
    ): ExamSubmission {
        $submission = ExamSubmission::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'attempt_number' => $attempt,
            'status' => 'in_progress',
            'started_at' => now()->subMinutes(18),
            'deadline_at' => now()->addMinutes(42),
            'client_token' => (string) Str::uuid(),
        ]);
        foreach ($exam->questions->take($answered) as $question) {
            ExamAnswer::create([
                'exam_submission_id' => $submission->id,
                'question_id' => $question->id,
                'answer_data' => [
                    'raw' => (int) ($question->content['correct_option'] ?? 0),
                ],
                'is_correct' => null,
                'points_awarded' => 0,
            ]);
        }

        return $submission;
    }

    private function submittedSubmission(
        Exam $exam,
        User $student
    ): ExamSubmission {
        $submission = ExamSubmission::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'submitted',
            'started_at' => now()->subHours(2),
            'deadline_at' => now()->subHour(),
            'client_token' => (string) Str::uuid(),
            'finished_at' => now()->subHour(),
            'feedback' => null,
        ]);
        foreach ($exam->questions as $question) {
            ExamAnswer::create([
                'exam_submission_id' => $submission->id,
                'question_id' => $question->id,
                'answer_data' => [
                    'raw' => $question->type === 'essay'
                        ? 'Resposta fictícia aguardando correção manual.'
                        : (int) ($question->content['correct_option'] ?? 0),
                ],
                'is_correct' => null,
                'points_awarded' => 0,
            ]);
        }

        return $submission;
    }

    private function material(
        Organization $organization,
        User $author,
        string $title,
        string $description,
        string $body,
        ?Discipline $discipline,
        string $status,
        Collection $classes
    ): LearningMaterial {
        $material = LearningMaterial::create([
            'organization_id' => $organization->id,
            'author_id' => $author->id,
            'title' => $title,
            'description' => $description,
            'body' => $body,
            'discipline_id' => $discipline?->id,
            'status' => $status,
        ]);
        $material->schoolClasses()->sync($classes->pluck('id')->all());

        return $material;
    }

    private function writeDemoScanImage(): void
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="1100" viewBox="0 0 800 1100">
  <rect width="800" height="1100" fill="#f8fafc"/>
  <rect x="45" y="45" width="710" height="1010" rx="8" fill="white" stroke="#0f5132" stroke-width="6"/>
  <text x="400" y="100" text-anchor="middle" font-family="Arial" font-size="28" font-weight="700" fill="#0f5132">CARTÃO-RESPOSTA DEMONSTRATIVO</text>
  <text x="90" y="150" font-family="Arial" font-size="18" fill="#334155">Escola Horizonte · Simulado Multidisciplinar</text>
  <text x="90" y="185" font-family="Arial" font-size="16" fill="#475569">Aluno: Estudante Fictício · Turma: 2º Ano A</text>
  <g font-family="Arial" font-size="15" fill="#1e293b">
    <text x="105" y="245">01</text><circle cx="170" cy="240" r="14" fill="#0f5132"/><circle cx="220" cy="240" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="270" cy="240" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="320" cy="240" r="14" fill="none" stroke="#64748b" stroke-width="2"/>
    <text x="105" y="300">02</text><circle cx="170" cy="295" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="220" cy="295" r="14" fill="#0f5132"/><circle cx="270" cy="295" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="320" cy="295" r="14" fill="none" stroke="#64748b" stroke-width="2"/>
    <text x="105" y="355">03</text><circle cx="170" cy="350" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="220" cy="350" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="270" cy="350" r="14" fill="#0f5132"/><circle cx="320" cy="350" r="14" fill="none" stroke="#64748b" stroke-width="2"/>
    <text x="105" y="410">04</text><circle cx="170" cy="405" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="220" cy="405" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="270" cy="405" r="14" fill="none" stroke="#64748b" stroke-width="2"/><circle cx="320" cy="405" r="14" fill="#0f5132"/>
  </g>
  <rect x="510" y="230" width="150" height="150" fill="#e2e8f0" stroke="#334155" stroke-width="3"/>
  <path d="M525 245h40v40h-40zm80 0h40v40h-40zm-80 80h40v40h-40zm50-30h20v20h-20zm30 30h40v40h-40z" fill="#0f172a"/>
  <text x="400" y="1010" text-anchor="middle" font-family="Arial" font-size="16" fill="#64748b">Imagem exclusivamente fictícia para testes de interface OMR</text>
</svg>
SVG;

        Storage::disk('public')->put('omr/demo/cartao-exemplo.svg', $svg);
    }

    private function printSummary(): void
    {
        $rows = [
            ['Instituições', Organization::withTrashed()->count()],
            ['Usuários', User::withTrashed()->count()],
            ['Turmas', SchoolClass::withoutGlobalScopes()->withTrashed()->count()],
            ['Questões', Question::withTrashed()->count()],
            ['Avaliações', Exam::withoutGlobalScopes()->withTrashed()->count()],
            ['Entregas', ExamSubmission::count()],
            ['Respostas', ExamAnswer::count()],
            ['Materiais', LearningMaterial::withTrashed()->count()],
            ['Leituras OMR', OmrScan::count()],
            ['Eventos', EventLog::count()],
            ['Auditorias', AuditLog::count()],
        ];

        $this->command?->newLine();
        $this->command?->info('Base demonstrativa criada com sucesso.');
        $this->command?->table(['Módulo', 'Registros'], $rows);
        $this->command?->line('Senha de todas as contas demonstrativas: password');
        $this->command?->line('Principal: institution@email.com / teacher@email.com / student@email.com / guardian@email.com');
        $this->command?->line('Coordenação: coordinator@email.com');
        $this->command?->line('Tenant Aurora: institution.aurora@email.com / teacher.aurora@email.com / student01.aurora@demo.avaliation.test');
        $this->command?->line('Tenant suspenso: institution.inactive@email.com');
    }
}
