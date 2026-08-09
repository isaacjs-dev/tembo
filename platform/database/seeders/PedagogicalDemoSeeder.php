<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\CourtesyGrant;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Question;
use App\Models\Revision;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\GamificationService;
use App\Services\MonthlyUsageService;
use App\Services\RevisionGraderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class PedagogicalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Os dados pedagógicos demonstrativos só podem ser gerados em local/testing.');
        }

        DB::transaction(function (): void {
            $organization = Organization::where('subdomain', 'escola-modelo')->firstOrFail();
            $teacher = User::where('email', 'teacher@email.com')->firstOrFail();
            $coordinator = User::where('email', 'coordinator@email.com')->firstOrFail();
            $students = User::where('organization_id', $organization->id)->where('type', 'student')->where('status', 'active')->limit(12)->get();
            $classes = SchoolClass::where('organization_id', $organization->id)->orderBy('id')->limit(3)->get();
            $questions = Question::where('organization_id', $organization->id)->limit(12)->get();

            $lessons = $this->lessons($organization, $teacher, $classes);
            $activities = $this->activities($organization, $teacher, $classes, $questions);
            $revisions = $this->revisions($organization, $teacher, $coordinator, $classes, $lessons, $activities);
            $this->attempts($revisions['published'], $students);
            $this->courtesies($organization, $teacher);
            $this->usage($teacher);
        });

        $this->command?->info('Aulas, atividades, revisões, gamificação, consumo e cortesias demonstrativas geradas.');
    }

    private function lessons(Organization $organization, User $teacher, $classes): array
    {
        $data = [
            ['Sistema decimal e resolução de problemas', 'published', now()->subDays(14), true],
            ['Leitura e interpretação de gráficos', 'published', now()->subDays(3), false],
            ['Introdução às frações equivalentes', 'draft', now()->addDays(5), true],
        ];
        $result = [];
        foreach ($data as $index => [$title, $status, $startsAt, $generateReview]) {
            $lesson = Lesson::create([
                'organization_id' => $organization->id, 'author_id' => $teacher->id,
                'discipline_id' => Question::where('organization_id', $organization->id)->whereNotNull('discipline_id')->value('discipline_id'),
                'title' => $title, 'objectives' => 'Compreender conceitos, aplicar estratégias e explicar o próprio raciocínio.',
                'content' => "Aula demonstrativa {$index}. Inclui abertura, explicação guiada, exemplos resolvidos, prática em duplas e fechamento.",
                'starts_at' => $startsAt, 'status' => $status, 'generate_review' => $generateReview,
                'published_at' => $status === 'published' ? $startsAt : null,
            ]);
            $lesson->schoolClasses()->sync($classes->take($index === 1 ? 2 : 1)->pluck('id'));
            $result[] = $lesson;
        }

        return $result;
    }

    private function activities(Organization $organization, User $teacher, $classes, $questions): array
    {
        $result = [];
        foreach ([
            ['Lista diagnóstica de Matemática', 'published', 'online', now()->subDays(7), now()->addDays(2)],
            ['Desafio colaborativo de gráficos', 'published', 'hybrid', now()->subDay(), now()->addWeek()],
            ['Atividade de recuperação', 'draft', 'paper', now()->addWeek(), now()->addWeeks(2)],
        ] as $index => [$title, $status, $modality, $availableAt, $dueAt]) {
            $activity = Activity::create([
                'organization_id' => $organization->id, 'author_id' => $teacher->id,
                'discipline_id' => $questions->first()?->discipline_id, 'title' => $title,
                'instructions' => 'Leia com atenção, registre o raciocínio e revise antes de enviar.',
                'available_at' => $availableAt, 'due_at' => $dueAt, 'max_attempts' => 2,
                'points' => 10, 'modality' => $modality, 'status' => $status,
                'generate_review' => $index !== 2, 'published_at' => $status === 'published' ? now()->subDays(2) : null,
            ]);
            $activity->schoolClasses()->sync($classes->take(2)->pluck('id'));
            $activity->questions()->sync($questions->slice($index * 2, 4)->values()->mapWithKeys(fn ($question, $order) => [
                $question->id => ['points' => 2.5, 'order' => $order],
            ])->all());
            $result[] = $activity;
        }

        return $result;
    }

    private function revisions(Organization $organization, User $teacher, User $coordinator, $classes, array $lessons, array $activities): array
    {
        $published = Revision::create([
            'organization_id' => $organization->id, 'author_id' => $teacher->id,
            'discipline_id' => $activities[0]->discipline_id, 'title' => 'Missão: dominar números e gráficos',
            'description' => 'Revisão completa com todos os formatos interativos disponíveis.', 'status' => 'published',
            'is_required' => false, 'timing' => 'independent', 'max_attempts' => 3, 'feedback_mode' => 'immediate',
            'gamification_enabled' => true, 'published_at' => now()->subDays(4), 'reviewed_by' => $coordinator->id,
        ]);
        $published->schoolClasses()->sync($classes->take(2)->pluck('id'));
        $published->sources()->create(['source_type' => Lesson::class, 'source_id' => $lessons[0]->id]);
        $published->sources()->create(['source_type' => Activity::class, 'source_id' => $activities[0]->id]);

        $items = [
            ['explanation', 'Relembre: valor posicional', ['body' => 'Em 3.482, o algarismo 4 representa quatro centenas.'], [], 0],
            ['multiple_choice', 'Qual número possui 5 centenas, 2 dezenas e 8 unidades?', ['options' => ['528', '5.028', '258', '582']], ['correct_option' => 0], 1],
            ['true_false', 'Todo gráfico precisa indicar o que seus eixos representam.', [], ['correct_option' => 0], 1],
            ['matching', 'Associe cada termo à sua função.', ['left' => ['titulo' => 'Título', 'legenda' => 'Legenda'], 'right' => ['assunto' => 'Informa o assunto', 'cores' => 'Explica cores e símbolos']], ['pairs' => ['titulo' => 'assunto', 'legenda' => 'cores']], 2],
            ['fill_blank', 'Complete: uma dezena possui ___ unidades.', [], ['accepted_answers' => ['10', 'dez']], 1],
            ['ordering', 'Ordene do menor para o maior.', ['items' => ['n12' => '12', 'n3' => '3', 'n20' => '20']], ['order' => ['n3', 'n12', 'n20']], 2],
            ['flashcard', 'Cartão de estudo', ['front' => 'O que é valor posicional?', 'back' => 'É o valor que um algarismo assume conforme sua posição no número.'], [], 0],
            ['short_answer', 'Qual é a capital do Espírito Santo?', [], ['accepted_answers' => ['Vitória', 'Vitoria']], 1],
        ];
        foreach ($items as $order => [$type, $prompt, $content, $solution, $points]) {
            $published->items()->create([
                'type' => $type, 'order' => $order, 'difficulty' => min(5, max(1, $order % 4 + 1)),
                'prompt' => $prompt, 'content' => $content, 'solution' => $solution,
                'explanation' => 'Confira o conceito no material de apoio.', 'hints' => ['Leia novamente o enunciado.', 'Elimine alternativas incompatíveis.'],
                'points' => $points, 'is_active' => true, 'updated_by' => $teacher->id,
            ]);
        }

        foreach ([
            ['Revisão aguardando coordenação', 'in_review', 'Revisar clareza e nível de dificuldade.'],
            ['Revisão devolvida para ajustes', 'changes_requested', 'Acrescentar uma explicação antes do exercício 2.'],
            ['Revisão temporariamente suspensa', 'suspended', 'Suspensa durante atualização do conteúdo.'],
            ['Rascunho de recuperação', 'draft', null],
        ] as [$title, $status, $notes]) {
            $revision = Revision::create([
                'organization_id' => $organization->id, 'author_id' => $teacher->id,
                'title' => $title, 'description' => 'Cenário demonstrativo do fluxo de revisão.', 'status' => $status,
                'timing' => 'after', 'max_attempts' => 2, 'feedback_mode' => 'end', 'gamification_enabled' => true,
                'reviewed_by' => in_array($status, ['changes_requested', 'suspended'], true) ? $coordinator->id : null,
                'review_notes' => $notes, 'published_at' => $status === 'suspended' ? now()->subWeek() : null,
            ]);
            $revision->schoolClasses()->sync($classes->take(1)->pluck('id'));
            $revision->items()->create(['type' => 'short_answer', 'order' => 0, 'prompt' => 'Explique o conceito estudado com suas palavras.',
                'content' => [], 'solution' => ['accepted_answers' => ['resposta demonstrativa']], 'points' => 1, 'updated_by' => $teacher->id]);
        }

        $exam = Exam::where('organization_id', $organization->id)->where('status', 'published')->first();
        if ($exam) {
            $preExam = Revision::create([
                'organization_id' => $organization->id, 'author_id' => $teacher->id, 'title' => 'Preparação obrigatória para a prova',
                'description' => 'Conclua antes de iniciar a avaliação vinculada.', 'status' => 'published', 'is_required' => true,
                'timing' => 'before', 'block_exam' => true, 'max_attempts' => 3, 'feedback_mode' => 'end', 'published_at' => now()->subDay(),
            ]);
            $preExam->schoolClasses()->sync($exam->schoolClasses()->pluck('school_classes.id'));
            $preExam->sources()->create(['source_type' => $exam->getMorphClass(), 'source_id' => $exam->id]);
            $preExam->items()->create(['type' => 'true_false', 'order' => 0, 'prompt' => 'Li as orientações e revisei o conteúdo.',
                'content' => [], 'solution' => ['correct_option' => 0], 'points' => 1, 'updated_by' => $teacher->id]);
        }

        return ['published' => $published];
    }

    private function attempts(Revision $revision, $students): void
    {
        $grader = app(RevisionGraderService::class);
        $questions = $revision->activeItems()->whereNotIn('type', ['explanation', 'example'])->get();
        foreach ($students->take(6) as $studentIndex => $student) {
            $attempt = $revision->attempts()->create([
                'student_id' => $student->id, 'organization_id' => $revision->organization_id,
                'attempt_number' => 1, 'status' => $studentIndex < 4 ? 'completed' : 'in_progress',
                'started_at' => now()->subDays(3)->addHours($studentIndex), 'last_activity_at' => now()->subHours($studentIndex + 1),
                'completed_at' => $studentIndex < 4 ? now()->subDays(2)->addHours($studentIndex) : null,
            ]);
            $earned = 0;
            foreach ($questions->take($studentIndex < 4 ? $questions->count() : 2) as $item) {
                $answer = match ($item->type) {
                    'multiple_choice', 'true_false' => $item->solution['correct_option'] ?? 0,
                    'matching' => $item->solution['pairs'] ?? [], 'ordering' => $item->solution['order'] ?? [],
                    'fill_blank', 'short_answer' => $item->solution['accepted_answers'][0] ?? '', default => 'reviewed',
                };
                if ($studentIndex === 3 && $item->type === 'multiple_choice') {
                    $answer = 99;
                }
                $grade = $grader->grade($item, $answer);
                $earned += $grade['points_awarded'];
                $attempt->responses()->create(['revision_item_id' => $item->id, 'answer' => is_array($answer) ? $answer : ['value' => $answer],
                    'is_correct' => $grade['is_correct'], 'points_awarded' => $grade['points_awarded'], 'item_snapshot' => $grader->snapshot($item),
                    'feedback' => $grade['feedback'], 'answered_at' => now()->subDays(2)]);
            }
            if ($attempt->status === 'completed') {
                $total = (float) $questions->sum('points');
                $attempt->update(['score' => $total > 0 ? round(($earned / $total) * 10, 2) : 10, 'total_points' => $total]);
                app(GamificationService::class)->reward($attempt->fresh());
            }
        }
    }

    private function courtesies(Organization $organization, User $teacher): void
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();
        $grant = CourtesyGrant::create(['target_scope' => 'organization', 'target_id' => $organization->id, 'status' => 'active',
            'starts_at' => now()->subWeek(), 'ends_at' => now()->addMonths(2), 'reason' => 'Demonstração de créditos pedagógicos extras.',
            'authorized_by' => $admin->id, 'metadata' => ['eligible_roles' => ['teacher']]]);
        $grant->benefits()->createMany([
            ['benefit_type' => 'credit', 'resource_key' => MonthlyUsageService::OMR_SCANS, 'quantity' => 100],
            ['benefit_type' => 'credit', 'resource_key' => MonthlyUsageService::EXAM_PUBLICATIONS, 'quantity' => 20],
            ['benefit_type' => 'feature', 'feature_key' => 'certificates'],
        ]);

        $plan = Plan::where('status', 'active')->orderByDesc('tier_level')->first();
        if ($plan) {
            $free = CourtesyGrant::create(['target_scope' => 'user', 'target_id' => $teacher->id, 'status' => 'scheduled',
                'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonths(2), 'reason' => 'Demonstração de plano temporário gratuito.',
                'authorized_by' => $admin->id]);
            $free->benefits()->create(['benefit_type' => 'plan', 'plan_id' => $plan->id]);
        }
    }

    private function usage(User $teacher): void
    {
        $usage = app(MonthlyUsageService::class);
        foreach ([MonthlyUsageService::OMR_SCANS => 7, MonthlyUsageService::EXAM_PUBLICATIONS => 2, MonthlyUsageService::QUESTIONS_CREATED => 18] as $resource => $amount) {
            $usage->consume($teacher, $resource, $amount, "demo:{$resource}", null, $teacher, ['source' => 'demo_seeder']);
        }
    }
}
