<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAttempt;
use App\Models\ActivityResponse;
use App\Models\AuditLog;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\QuestionResourceVersion;
use App\Services\ActivityGraderService;
use App\Services\PedagogicalDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentPedagogicalController extends Controller
{
    public function __construct(
        private readonly PedagogicalDeliveryService $delivery,
        private readonly ActivityGraderService $grader,
    ) {}

    public function index(Request $request): View
    {
        return view('student.pedagogical.index', [
            'lessons' => $this->delivery->lessonsFor($request->user()),
            'activities' => $this->delivery->activitiesFor($request->user()),
        ]);
    }

    public function lesson(Request $request, int $lesson): View
    {
        $lesson = $this->delivery->findLesson($request->user(), $lesson);
        $progress = $this->delivery->ensureLessonProgress($lesson, $request->user());

        return view('student.pedagogical.lesson', compact('lesson', 'progress'));
    }

    public function completeLesson(Request $request, int $lesson): RedirectResponse
    {
        $lesson = $this->delivery->findLesson($request->user(), $lesson);
        DB::transaction(function () use ($lesson, $request): void {
            $progress = LessonProgress::query()->where('lesson_id', $lesson->id)
                ->where('student_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            if ($progress->status !== 'completed') {
                $progress->update(['status' => 'completed', 'completed_at' => now(), 'last_activity_at' => now()]);
                AuditLog::log('lesson_completed', Lesson::class, $lesson->id, [
                    'organization_id' => $request->user()->organization_id, 'student_id' => $request->user()->id,
                ]);
            }
        }, 3);

        return back()->with('status', 'Aula concluída.');
    }

    public function activity(Request $request, int $activity): View
    {
        $activity = $this->delivery->findActivity($request->user(), $activity);
        $attempts = ActivityAttempt::query()->where('activity_id', $activity->id)
            ->where('student_id', $request->user()->id)->latest('attempt_number')->get();

        return view('student.pedagogical.activity', compact('activity', 'attempts'));
    }

    public function startActivity(Request $request, int $activity): RedirectResponse
    {
        $activity = $this->delivery->findActivity($request->user(), $activity);
        $attempt = $this->delivery->ensureAttempt($activity, $request->user());

        return redirect()->route('student.pedagogical.activities.execute', [$activity->id, $attempt->id]);
    }

    public function executeActivity(Request $request, int $activity, int $attempt): View|RedirectResponse
    {
        $activity = $this->delivery->findActivity($request->user(), $activity);
        $attempt = $this->attempt($request, $activity, $attempt)->load('responses');
        if ($attempt->status !== 'in_progress') {
            return redirect()->route('student.pedagogical.activities.result', [$activity->id, $attempt->id]);
        }
        if ($activity->due_at && now()->isAfter($activity->due_at)) {
            $attempt = DB::transaction(function () use ($attempt, $request, $activity): ActivityAttempt {
                $locked = ActivityAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
                if (in_array($locked->status, ['submitted', 'graded'], true)) {
                    return $locked;
                }

                $graded = $this->grader->grade($locked);
                $this->auditSubmission($request, $activity, $graded);

                return $graded;
            }, 3);

            return redirect()->route('student.pedagogical.activities.result', [$activity->id, $attempt->id])
                ->with('status', 'O prazo terminou; o progresso salvo foi enviado.');
        }

        return view('student.pedagogical.activity_execute', compact('activity', 'attempt'));
    }

    public function saveActivity(Request $request, int $activity, int $attempt): RedirectResponse
    {
        $activity = $this->delivery->findActivity($request->user(), $activity);
        $attempt = $this->attempt($request, $activity, $attempt);
        $answers = $this->validatedAnswers($request);
        DB::transaction(function () use ($attempt, $activity, $answers): void {
            $locked = ActivityAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->assertWritable($activity, $locked);
            $this->persistResponses($locked, $answers);
        }, 3);

        return back()->with('status', 'Progresso salvo.');
    }

    public function submitActivity(Request $request, int $activity, int $attempt): RedirectResponse
    {
        $activity = $this->delivery->findActivity($request->user(), $activity);
        $attempt = $this->attempt($request, $activity, $attempt);
        $answers = $this->validatedAnswers($request);
        $attempt = DB::transaction(function () use ($attempt, $activity, $answers, $request): ActivityAttempt {
            $locked = ActivityAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (in_array($locked->status, ['submitted', 'graded'], true)) {
                return $locked;
            }
            $this->assertWritable($activity, $locked);
            $this->persistResponses($locked, $answers);
            $graded = $this->grader->grade($locked);
            $this->auditSubmission($request, $activity, $graded);

            return $graded;
        }, 3);

        return redirect()->route('student.pedagogical.activities.result', [$activity->id, $attempt->id]);
    }

    public function activityResult(Request $request, int $activity, int $attempt): View
    {
        $activity = $this->delivery->findActivity($request->user(), $activity);
        $attempt = $this->attempt($request, $activity, $attempt)->load('responses');
        abort_if($attempt->status === 'in_progress', 409);

        return view('student.pedagogical.activity_result', compact('activity', 'attempt'));
    }

    public function activityResource(Request $request, int $activity, int $attempt, int $question, QuestionResourceVersion $version): StreamedResponse
    {
        $activity = $this->delivery->findActivity($request->user(), $activity);
        $attempt = $this->attempt($request, $activity, $attempt);
        $questionSnapshot = collect($attempt->content_snapshot['questions'] ?? [])->firstWhere('key', $question);
        abort_unless(is_array($questionSnapshot), 404);
        $snapshot = collect($questionSnapshot['resources'] ?? [])
            ->first(fn (array $resource): bool => (int) ($resource['resource_version_id'] ?? 0) === (int) $version->id);
        abort_unless(is_array($snapshot), 404);
        $disk = data_get($snapshot, 'file.storage_disk');
        $path = data_get($snapshot, 'file.storage_path');
        abort_unless(is_string($disk) && is_string($path), 404);
        $version->loadMissing('resource');
        abort_unless($version->resource
            && (int) $version->resource->id === (int) ($snapshot['resource_id'] ?? 0)
            && (int) $version->resource->organization_id === (int) $activity->organization_id
            && $version->storage_disk === $disk && $version->storage_path === $path, 404);
        $sha256 = data_get($snapshot, 'file.sha256');
        abort_unless(! $sha256 || hash_equals((string) $sha256, (string) $version->sha256), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);
        $inline = str_starts_with((string) $version->mime_type, 'image/');

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => $version->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.basename($path).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function attempt(Request $request, Activity $activity, int $attempt): ActivityAttempt
    {
        return ActivityAttempt::query()->whereKey($attempt)->where('activity_id', $activity->id)
            ->where('organization_id', $request->user()->organization_id)
            ->where('student_id', $request->user()->id)->firstOrFail();
    }

    private function validatedAnswers(Request $request): array
    {
        return $request->validate(['answers' => ['nullable', 'array'], 'answers.*' => ['nullable', 'string', 'max:20000']])['answers'] ?? [];
    }

    private function assertWritable(Activity $activity, ActivityAttempt $attempt): void
    {
        if ($attempt->status !== 'in_progress') {
            throw ValidationException::withMessages(['attempt' => 'Esta tentativa não aceita novas respostas.']);
        }
        if ($activity->due_at && now()->isAfter($activity->due_at)) {
            throw ValidationException::withMessages(['attempt' => 'O prazo desta atividade foi encerrado.']);
        }
    }

    private function persistResponses(ActivityAttempt $attempt, array $answers): void
    {
        $questions = collect($attempt->content_snapshot['questions'] ?? [])->keyBy(fn ($question) => (int) $question['key']);
        foreach ($answers as $key => $answer) {
            $question = $questions->get((int) $key);
            if (! $question) {
                throw ValidationException::withMessages(["answers.$key" => 'Questão inválida para esta tentativa.']);
            }
            ActivityResponse::query()->updateOrCreate([
                'activity_attempt_id' => $attempt->id, 'snapshot_question_key' => (int) $key,
            ], [
                'question_id' => $question['question_id'] ?? null, 'answer' => ['value' => $answer], 'answered_at' => now(),
            ]);
        }
        $attempt->update(['last_activity_at' => now()]);
    }

    private function auditSubmission(Request $request, Activity $activity, ActivityAttempt $attempt): void
    {
        AuditLog::log('activity_submitted', Activity::class, $activity->id, [
            'organization_id' => $request->user()->organization_id, 'student_id' => $request->user()->id,
            'activity_attempt_id' => $attempt->id, 'attempt_number' => $attempt->attempt_number,
        ]);
    }
}
