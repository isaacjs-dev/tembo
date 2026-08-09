<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Discipline;
use App\Models\Organization;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademicRelationshipService
{
    public function syncTeacher(
        User $teacher,
        Organization $organization,
        array $classIds,
        array $studentIds,
        array $disciplineIds,
        User $actor,
    ): void {
        $organizationId = (int) $organization->id;
        abort_unless($actor->canUseOrganizationContext($organizationId), 403);
        abort_unless(
            $this->actorCanManageOrganization($actor, $organization)
                || ($organization->isPersonalWorkspace() && $actor->is($teacher)),
            403,
        );
        abort_unless($teacher->belongsToActiveOrganization($organizationId, 'teacher'), 404);

        $classIds = $this->validatedClassIds($organizationId, $classIds);
        $studentIds = $this->validatedStudentIds($organizationId, $studentIds);
        $disciplineIds = $this->validatedDisciplineIds($organizationId, $disciplineIds);

        DB::transaction(function () use (
            $teacher,
            $organizationId,
            $classIds,
            $studentIds,
            $disciplineIds,
            $actor,
        ): void {
            $before = [
                'classes' => $this->teacherClassIds($teacher->id, $organizationId),
                'students' => $this->teacherStudentIds($teacher->id, $organizationId),
                'disciplines' => $this->teacherDisciplineIds($teacher->id, $organizationId),
            ];

            $currentOrganizationClassIds = SchoolClass::withoutGlobalScopes()
                ->where('organization_id', $organizationId)
                ->pluck('id');
            DB::table('class_teacher')
                ->where('user_id', $teacher->id)
                ->whereIn('school_class_id', $currentOrganizationClassIds)
                ->delete();
            $this->insertClassTeachers($classIds, [(int) $teacher->id]);

            DB::table('teacher_student')
                ->where('organization_id', $organizationId)
                ->where('teacher_id', $teacher->id)
                ->delete();
            $this->insertTeacherStudents(
                $organizationId,
                [(int) $teacher->id],
                $studentIds,
                (int) $actor->id,
            );

            DB::table('discipline_teacher')
                ->where('organization_id', $organizationId)
                ->where('user_id', $teacher->id)
                ->delete();
            $this->insertDisciplineTeachers(
                $organizationId,
                [(int) $teacher->id],
                $disciplineIds,
                (int) $actor->id,
            );

            foreach ($classIds as $classId) {
                $this->linkClassStudentTeachersById($classId, (int) $actor->id);
            }

            AuditLog::log('academic_relations_synced', User::class, $teacher->id, [
                'organization_id' => $organizationId,
                'before' => $before,
                'after' => [
                    'classes' => $classIds,
                    'students' => $this->teacherStudentIds($teacher->id, $organizationId),
                    'disciplines' => $disciplineIds,
                ],
            ]);
        });
    }

    public function syncClass(
        SchoolClass $schoolClass,
        array $teacherIds,
        array $disciplineIds,
        User $actor,
    ): void {
        $organizationId = (int) $schoolClass->organization_id;
        abort_unless($actor->canUseOrganizationContext($organizationId), 403);
        abort_unless($this->actorCanManageClass($actor, $schoolClass), 403);

        $teacherIds = $this->validatedTeacherIds($organizationId, $teacherIds);
        $disciplineIds = $this->validatedDisciplineIds($organizationId, $disciplineIds);

        DB::transaction(function () use (
            $schoolClass,
            $organizationId,
            $teacherIds,
            $disciplineIds,
            $actor,
        ): void {
            $before = [
                'teachers' => $schoolClass->teachers()->pluck('users.id')->map(fn ($id) => (int) $id)->all(),
                'disciplines' => $schoolClass->disciplines()->pluck('disciplines.id')->map(fn ($id) => (int) $id)->all(),
            ];

            DB::table('class_teacher')->where('school_class_id', $schoolClass->id)->delete();
            $this->insertClassTeachers([(int) $schoolClass->id], $teacherIds);

            DB::table('class_discipline')
                ->where('organization_id', $organizationId)
                ->where('school_class_id', $schoolClass->id)
                ->delete();
            $this->insertClassDisciplines(
                $organizationId,
                (int) $schoolClass->id,
                $disciplineIds,
                (int) $actor->id,
            );

            $this->linkClassStudentTeachersById((int) $schoolClass->id, (int) $actor->id);

            AuditLog::log('academic_relations_synced', SchoolClass::class, $schoolClass->id, [
                'organization_id' => $organizationId,
                'before' => $before,
                'after' => ['teachers' => $teacherIds, 'disciplines' => $disciplineIds],
            ]);
        });
    }

    public function linkClassStudentTeachers(SchoolClass $schoolClass, User $student, User $actor): void
    {
        $organizationId = (int) $schoolClass->organization_id;
        abort_unless($actor->canUseOrganizationContext($organizationId), 403);
        abort_unless($this->actorCanManageClass($actor, $schoolClass), 403);
        abort_unless($student->belongsToActiveOrganization($organizationId, 'student'), 403);
        $this->linkClassStudentTeachersById((int) $schoolClass->id, (int) $actor->id);
    }

    private function actorCanManageOrganization(User $actor, Organization $organization): bool
    {
        return in_array(
            $actor->roleInOrganization((int) $organization->id),
            ['admin', 'institution_admin', 'global_admin'],
            true,
        ) || ($organization->isPersonalWorkspace() && (int) $organization->owner_user_id === (int) $actor->id);
    }

    private function actorCanManageClass(User $actor, SchoolClass $schoolClass): bool
    {
        return in_array(
            $actor->roleInOrganization((int) $schoolClass->organization_id),
            ['admin', 'institution_admin', 'global_admin'],
            true,
        ) || ($schoolClass->owner_type === 'user' && (int) $schoolClass->owner_id === (int) $actor->id);
    }

    /** @return array<int, int> */
    private function validatedClassIds(int $organizationId, array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        $valid = SchoolClass::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $ids)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertComplete($ids, $valid, 'class_ids', 'Uma ou mais turmas não pertencem ao workspace.');

        return $ids;
    }

    /** @return array<int, int> */
    private function validatedTeacherIds(int $organizationId, array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        $valid = User::query()->memberOfOrganization($organizationId, 'teacher')
            ->whereIn('users.id', $ids)
            ->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $this->assertComplete($ids, $valid, 'teacher_ids', 'Um ou mais professores não pertencem ao workspace.');

        return $ids;
    }

    /** @return array<int, int> */
    private function validatedStudentIds(int $organizationId, array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        $valid = User::query()->memberOfOrganization($organizationId, 'student')
            ->whereIn('users.id', $ids)
            ->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $this->assertComplete($ids, $valid, 'student_ids', 'Um ou mais alunos não pertencem ao workspace.');

        return $ids;
    }

    /** @return array<int, int> */
    private function validatedDisciplineIds(int $organizationId, array $ids): array
    {
        $ids = $this->normalizeIds($ids);
        $valid = Discipline::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereIn('id', $ids)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertComplete($ids, $valid, 'discipline_ids', 'Uma ou mais disciplinas não pertencem ao workspace.');

        return $ids;
    }

    private function linkClassStudentTeachersById(int $classId, int $actorId): void
    {
        $schoolClass = SchoolClass::withoutGlobalScopes()->findOrFail($classId);
        $organizationId = (int) $schoolClass->organization_id;
        $assignedTeacherIds = DB::table('class_teacher')
            ->where('school_class_id', $classId)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $teacherIds = User::query()
            ->memberOfOrganization($organizationId, 'teacher')
            ->whereIn('users.id', $assignedTeacherIds)
            ->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        if ($schoolClass->owner_type === 'user') {
            $owner = User::find($schoolClass->owner_id);
            if ($owner?->belongsToActiveOrganization($organizationId, 'teacher')) {
                $teacherIds[] = (int) $owner->id;
            }
        }

        $enrolledStudentIds = DB::table('class_student')
            ->where('school_class_id', $classId)
            ->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $studentIds = User::query()
            ->memberOfOrganization($organizationId, 'student')
            ->whereIn('users.id', $enrolledStudentIds)
            ->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $this->insertTeacherStudents(
            $organizationId,
            array_values(array_unique($teacherIds)),
            $studentIds,
            $actorId,
        );
    }

    private function insertClassTeachers(array $classIds, array $teacherIds): void
    {
        $rows = [];
        foreach ($classIds as $classId) {
            foreach ($teacherIds as $teacherId) {
                $rows[] = [
                    'school_class_id' => $classId,
                    'user_id' => $teacherId,
                    'assigned_at' => now(),
                ];
            }
        }
        if ($rows !== []) {
            DB::table('class_teacher')->insertOrIgnore($rows);
        }
    }

    private function insertTeacherStudents(
        int $organizationId,
        array $teacherIds,
        array $studentIds,
        int $actorId,
    ): void {
        $rows = [];
        foreach ($teacherIds as $teacherId) {
            foreach ($studentIds as $studentId) {
                $rows[] = [
                    'organization_id' => $organizationId,
                    'teacher_id' => $teacherId,
                    'student_id' => $studentId,
                    'linked_by' => $actorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if ($rows !== []) {
            DB::table('teacher_student')->insertOrIgnore($rows);
        }
    }

    private function insertDisciplineTeachers(
        int $organizationId,
        array $teacherIds,
        array $disciplineIds,
        int $actorId,
    ): void {
        $rows = [];
        foreach ($teacherIds as $teacherId) {
            foreach ($disciplineIds as $disciplineId) {
                $rows[] = [
                    'organization_id' => $organizationId,
                    'discipline_id' => $disciplineId,
                    'user_id' => $teacherId,
                    'assigned_by' => $actorId,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if ($rows !== []) {
            DB::table('discipline_teacher')->insertOrIgnore($rows);
        }
    }

    private function insertClassDisciplines(
        int $organizationId,
        int $classId,
        array $disciplineIds,
        int $actorId,
    ): void {
        $rows = array_map(fn (int $disciplineId): array => [
            'organization_id' => $organizationId,
            'school_class_id' => $classId,
            'discipline_id' => $disciplineId,
            'assigned_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ], $disciplineIds);
        if ($rows !== []) {
            DB::table('class_discipline')->insertOrIgnore($rows);
        }
    }

    /** @return array<int, int> */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => (int) $id > 0))));
    }

    private function assertComplete(array $requested, array $valid, string $field, string $message): void
    {
        if (array_diff($requested, $valid) !== []) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /** @return array<int, int> */
    private function teacherClassIds(int $teacherId, int $organizationId): array
    {
        return DB::table('class_teacher')
            ->join('school_classes', 'school_classes.id', '=', 'class_teacher.school_class_id')
            ->where('class_teacher.user_id', $teacherId)
            ->where('school_classes.organization_id', $organizationId)
            ->pluck('class_teacher.school_class_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<int, int> */
    private function teacherStudentIds(int $teacherId, int $organizationId): array
    {
        return DB::table('teacher_student')
            ->where('organization_id', $organizationId)
            ->where('teacher_id', $teacherId)
            ->pluck('student_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<int, int> */
    private function teacherDisciplineIds(int $teacherId, int $organizationId): array
    {
        return DB::table('discipline_teacher')
            ->where('organization_id', $organizationId)
            ->where('user_id', $teacherId)
            ->pluck('discipline_id')->map(fn ($id) => (int) $id)->all();
    }
}
