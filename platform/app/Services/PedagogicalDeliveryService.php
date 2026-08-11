<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityAttempt;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PedagogicalDeliveryService
{
    public function __construct(private readonly QuestionResourceSnapshotService $resources) {}

    public function lessonsFor(User $student): LengthAwarePaginator
    {
        return $this->lessonQuery($student)->paginate(12, ['*'], 'lessons_page');
    }

    public function activitiesFor(User $student): LengthAwarePaginator
    {
        return $this->activityQuery($student)->paginate(12, ['*'], 'activities_page');
    }

    public function findLesson(User $student, int $id): Lesson
    {
        return $this->lessonQuery($student)->whereKey($id)->firstOrFail();
    }

    public function findActivity(User $student, int $id): Activity
    {
        return $this->activityQuery($student)->whereKey($id)->firstOrFail();
    }

    private function lessonQuery(User $student): Builder
    {
        return Lesson::query()
            ->where('organization_id', $student->organization_id)
            ->where(function (Builder $query) use ($student): void {
                $query->whereHas('progress', fn (Builder $progress) => $progress->where('student_id', $student->id))
                    ->orWhere(function (Builder $available) use ($student): void {
                        $available->where('status', 'published')
                            ->where(fn (Builder $date) => $date->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                            ->whereHas('schoolClasses.students', fn (Builder $students) => $students->where('users.id', $student->id));
                    });
            })
            ->with(['author:id,name', 'discipline:id,name', 'progress' => fn ($query) => $query->where('student_id', $student->id)])
            ->latest('starts_at');
    }

    private function activityQuery(User $student): Builder
    {
        return Activity::query()
            ->where('organization_id', $student->organization_id)
            ->where(function (Builder $query) use ($student): void {
                $query->whereHas('attempts', fn (Builder $attempts) => $attempts->where('student_id', $student->id))
                    ->orWhere(function (Builder $available) use ($student): void {
                        $available->where('status', 'published')
                            ->where(fn (Builder $date) => $date->whereNull('available_at')->orWhere('available_at', '<=', now()))
                            ->whereHas('schoolClasses.students', fn (Builder $students) => $students->where('users.id', $student->id));
                    });
            })
            ->with(['author:id,name', 'discipline:id,name', 'attempts' => fn ($query) => $query->where('student_id', $student->id)->latest('attempt_number')])
            ->latest('available_at');
    }

    public function lessonSnapshot(Lesson $lesson): array
    {
        return [
            'schema_version' => 1,
            'lesson' => [
                'id' => (int) $lesson->id, 'title' => $lesson->title, 'objectives' => $lesson->objectives,
                'content' => $lesson->content, 'attachments' => $lesson->attachments ?? [],
                'starts_at' => $lesson->starts_at?->toIso8601String(),
            ],
        ];
    }

    public function activitySnapshot(Activity $activity): array
    {
        $activity->loadMissing('questions.resourceLinks.resource', 'questions.resourceLinks.version');
        $questions = $activity->questions->values();
        $points = (float) $activity->points;
        $totalCents = (int) round($points * 100);
        $baseCents = $questions->isEmpty() ? 0 : intdiv($totalCents, $questions->count());
        $remainder = $questions->isEmpty() ? 0 : $totalCents % $questions->count();

        return [
            'schema_version' => 1,
            'activity' => [
                'id' => (int) $activity->id, 'title' => $activity->title, 'instructions' => $activity->instructions,
                'modality' => $activity->modality, 'available_at' => $activity->available_at?->toIso8601String(),
                'due_at' => $activity->due_at?->toIso8601String(), 'points' => $points,
            ],
            'questions' => $questions->map(fn (Question $question, int $index): array => [
                'key' => (int) $question->id,
                'question_id' => (int) $question->id,
                'type' => $question->type,
                'content' => $question->content,
                'points' => ($baseCents + ($index < $remainder ? 1 : 0)) / 100,
                'resources' => $this->resources->forQuestion($question, true),
            ])->all(),
        ];
    }

    public function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function ensureLessonProgress(Lesson $lesson, User $student): LessonProgress
    {
        return DB::transaction(function () use ($lesson, $student): LessonProgress {
            $lesson = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            $progress = LessonProgress::query()->where('lesson_id', $lesson->id)
                ->where('student_id', $student->id)->lockForUpdate()->first();

            if ($progress) {
                $progress->update(['last_activity_at' => now()]);

                return $progress;
            }

            $snapshot = $this->lessonSnapshot($lesson);

            return LessonProgress::create([
                'lesson_id' => $lesson->id, 'student_id' => $student->id,
                'organization_id' => $student->organization_id, 'status' => 'in_progress',
                'content_snapshot' => $snapshot, 'snapshot_hash' => $this->hash($snapshot),
                'started_at' => now(), 'last_activity_at' => now(),
            ]);
        }, 3);
    }

    public function ensureAttempt(Activity $activity, User $student): ActivityAttempt
    {
        return DB::transaction(function () use ($activity, $student): ActivityAttempt {
            $activity = Activity::query()->lockForUpdate()->findOrFail($activity->id);
            DB::table('users')->where('id', $student->id)->lockForUpdate()->first();
            $attempts = ActivityAttempt::query()->where('activity_id', $activity->id)
                ->where('student_id', $student->id)->lockForUpdate()->get();
            if ($current = $attempts->firstWhere('status', 'in_progress')) {
                return $current;
            }
            abort_if($activity->status !== 'published' || ($activity->due_at && now()->isAfter($activity->due_at)), 409, 'Esta atividade não aceita novas tentativas.');
            abort_if($attempts->count() >= (int) $activity->max_attempts, 409, 'Todas as tentativas foram utilizadas.');
            $snapshot = $this->activitySnapshot($activity);

            return ActivityAttempt::create([
                'organization_id' => $student->organization_id, 'activity_id' => $activity->id, 'student_id' => $student->id,
                'attempt_number' => ((int) $attempts->max('attempt_number')) + 1, 'status' => 'in_progress',
                'content_snapshot' => $snapshot, 'snapshot_hash' => $this->hash($snapshot),
                'total_points' => (float) data_get($snapshot, 'activity.points', 0), 'started_at' => now(), 'last_activity_at' => now(),
            ]);
        }, 3);
    }
}
