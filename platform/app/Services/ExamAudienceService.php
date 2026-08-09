<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Discipline;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamAudienceService
{
    /** @return array{classes: Collection, students: Collection, disciplines: Collection} */
    public function optionsFor(User $user): array
    {
        $organizationId = (int) $user->organization_id;
        $teacherScoped = $user->hasWorkspaceRole('teacher');

        $classes = SchoolClass::query()
            ->where('organization_id', $organizationId)
            ->when($teacherScoped, function (Builder $query) use ($user): void {
                $query->where(function (Builder $scope) use ($user): void {
                    $scope->where(function (Builder $owner) use ($user): void {
                        $owner->where('owner_type', 'user')->where('owner_id', $user->id);
                    })->orWhereHas('teachers', fn (Builder $teachers) => $teachers->where('users.id', $user->id));
                });
            })
            ->orderBy('name')
            ->get();

        $classIds = $classes->pluck('id');

        $students = User::query()
            ->memberOfOrganization($organizationId, 'student')
            ->when($teacherScoped, function (Builder $query) use ($user, $classIds): void {
                $query->where(function (Builder $scope) use ($user, $classIds): void {
                    $scope->whereIn('users.id', DB::table('teacher_student')
                        ->select('student_id')
                        ->where('organization_id', $user->organization_id)
                        ->where('teacher_id', $user->id));

                    if ($classIds->isNotEmpty()) {
                        $scope->orWhereHas('schoolClasses', fn (Builder $classes) => $classes
                            ->whereIn('school_classes.id', $classIds));
                    }
                });
            })
            ->orderBy('name')
            ->get();

        $disciplines = Discipline::query()
            ->where('organization_id', $organizationId)
            ->when($teacherScoped, function (Builder $query) use ($user, $classIds): void {
                $query->where(function (Builder $scope) use ($user, $classIds): void {
                    $scope->whereIn('disciplines.id', DB::table('discipline_teacher')
                        ->select('discipline_id')
                        ->where('organization_id', $user->organization_id)
                        ->where('user_id', $user->id));

                    if ($classIds->isNotEmpty()) {
                        $scope->orWhereHas('schoolClasses', fn (Builder $classes) => $classes
                            ->whereIn('school_classes.id', $classIds));
                    }
                });
            })
            ->orderBy('name')
            ->get();

        return compact('classes', 'students', 'disciplines');
    }

    public function sync(
        Exam $exam,
        User $actor,
        ?int $disciplineId,
        array $classIds,
        array $studentIds,
    ): void {
        abort_unless((int) $exam->organization_id === (int) $actor->organization_id, 403);

        $options = $this->optionsFor($actor);
        $classIds = $this->validatedIds($classIds, $options['classes'], 'class_ids', 'Uma ou mais turmas não estão disponíveis neste workspace.');
        $studentIds = $this->validatedIds($studentIds, $options['students'], 'student_ids', 'Um ou mais alunos não estão disponíveis neste workspace.');

        if ($disciplineId !== null && ! $options['disciplines']->contains('id', $disciplineId)) {
            throw ValidationException::withMessages([
                'discipline_id' => 'A disciplina selecionada não está disponível neste workspace.',
            ]);
        }

        DB::transaction(function () use ($exam, $actor, $disciplineId, $classIds, $studentIds): void {
            $before = [
                'discipline_id' => $exam->discipline_id ? (int) $exam->discipline_id : null,
                'class_ids' => $exam->schoolClasses()->pluck('school_classes.id')->map(fn ($id) => (int) $id)->all(),
                'student_ids' => $exam->students()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
            ];

            $exam->update(['discipline_id' => $disciplineId]);
            $exam->schoolClasses()->sync($classIds);
            $exam->students()->syncWithPivotValues($studentIds, [
                'organization_id' => $exam->organization_id,
                'assigned_by' => $actor->id,
            ]);

            AuditLog::log('exam_audience_updated', Exam::class, $exam->id, [
                'before' => $before,
                'after' => [
                    'discipline_id' => $disciplineId,
                    'class_ids' => $classIds,
                    'student_ids' => $studentIds,
                ],
            ]);
        });
    }

    /** @return array<int, int> */
    public function studentIds(Exam $exam): array
    {
        $classIds = $exam->schoolClasses()->pluck('school_classes.id');
        $classStudentIds = $classIds->isEmpty()
            ? collect()
            : User::query()
                ->memberOfOrganization((int) $exam->organization_id, 'student')
                ->whereHas('schoolClasses', fn (Builder $query) => $query->whereIn('school_classes.id', $classIds))
                ->pluck('users.id');

        $directStudentIds = User::query()
            ->memberOfOrganization((int) $exam->organization_id, 'student')
            ->whereIn('users.id', $exam->students()->select('users.id'))
            ->pluck('users.id');

        return $classStudentIds
            ->merge($directStudentIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    private function validatedIds(array $ids, Collection $allowed, string $field, string $message): array
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values()->all();
        $allowedIds = $allowed->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (array_diff($ids, $allowedIds) !== []) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $ids;
    }
}
