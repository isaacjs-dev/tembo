<?php

namespace App\Http\Controllers;

use App\Models\QuestionResourceVersion;
use App\Models\Revision;
use App\Models\RevisionAttempt;
use App\Models\RevisionItem;
use App\Models\RevisionResponse;
use App\Models\StudentGamificationProfile;
use App\Services\GamificationService;
use App\Services\RevisionAttemptSnapshotService;
use App\Services\RevisionGraderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentRevisionController extends Controller
{
    public function index(Request $request): View
    {
        $classIds = $request->user()->schoolClasses()->pluck('school_classes.id');
        $revisions = Revision::query()->where('organization_id', $request->user()->organization_id)->where('status', 'published')
            ->whereHas('schoolClasses', fn ($q) => $q->whereIn('school_classes.id', $classIds))
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>=', now()))->with(['discipline', 'schoolClasses'])
            ->withCount('activeItems')->latest('published_at')->paginate(12);
        $attempts = RevisionAttempt::where('student_id', $request->user()->id)->whereIn('revision_id', $revisions->pluck('id'))->latest()->get()->unique('revision_id')->keyBy('revision_id');
        $profile = StudentGamificationProfile::query()->where('organization_id', $request->user()->organization_id)
            ->where('student_id', $request->user()->id)->first();

        return view('student.revisions.index', compact('revisions', 'attempts', 'profile'));
    }

    public function show(Request $request, Revision $revision): View
    {
        $this->ensureAvailable($request, $revision);
        $revision->loadCount('activeItems');
        $attempts = $revision->attempts()->where('student_id', $request->user()->id)->latest('attempt_number')->get();

        return view('student.revisions.show', compact('revision', 'attempts'));
    }

    public function start(Request $request, Revision $revision, RevisionAttemptSnapshotService $snapshots): RedirectResponse
    {
        $this->ensureAvailable($request, $revision);
        $attempt = DB::transaction(function () use ($request, $revision, $snapshots) {
            $revision = Revision::query()->lockForUpdate()->findOrFail($revision->id);
            $this->ensureAvailable($request, $revision);
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $attempts = $revision->attempts()->where('student_id', $request->user()->id)->lockForUpdate()->get();
            if ($current = $attempts->firstWhere('status', 'in_progress')) {
                return $current;
            }
            if ($attempts->count() >= $revision->max_attempts) {
                throw ValidationException::withMessages(['attempt' => 'Você já usou todas as tentativas desta revisão.']);
            }

            $snapshot = $snapshots->build($revision);

            return $revision->attempts()->create(['student_id' => $request->user()->id, 'organization_id' => $request->user()->organization_id,
                'content_snapshot' => $snapshot, 'snapshot_hash' => $snapshots->hash($snapshot),
                'attempt_number' => ((int) $attempts->max('attempt_number')) + 1, 'status' => 'in_progress', 'started_at' => now(), 'last_activity_at' => now()]);
        }, 3);

        return redirect()->route('student.revisions.execute', [$revision, $attempt]);
    }

    public function execute(Request $request, Revision $revision, RevisionAttempt $attempt, RevisionAttemptSnapshotService $snapshots): View
    {
        $this->ensureAttempt($request, $revision, $attempt);
        $attempt = $snapshots->ensure($attempt, $revision);
        $attempt->load('responses');
        $items = $snapshots->items($attempt)->map(function (array $snapshot): RevisionItem {
            $item = new RevisionItem;
            $item->forceFill($snapshot);

            return $item;
        });
        $revisionSnapshot = data_get($attempt->content_snapshot, 'revision', []);
        $revision->forceFill([
            'title' => $revisionSnapshot['title'] ?? $revision->title,
            'description' => $revisionSnapshot['description'] ?? $revision->description,
            'feedback_mode' => $revisionSnapshot['feedback_mode'] ?? $revision->feedback_mode,
        ]);
        $revision->setRelation('activeItems', $items);

        return view('student.revisions.execute', compact('revision', 'attempt', 'items', 'revisionSnapshot'));
    }

    public function resource(
        Request $request,
        Revision $revision,
        RevisionAttempt $attempt,
        int $item,
        QuestionResourceVersion $version,
        RevisionAttemptSnapshotService $snapshots,
    ): StreamedResponse {
        $this->ensureAttempt($request, $revision, $attempt);
        $attempt = $snapshots->ensure($attempt, $revision);
        $itemSnapshot = $snapshots->item($attempt, $item);
        abort_unless(is_array($itemSnapshot), 404);
        $snapshot = collect(data_get($itemSnapshot, 'content.resources', []))
            ->first(fn (array $resource): bool => (int) ($resource['resource_version_id'] ?? 0) === (int) $version->id);
        abort_unless(is_array($snapshot), 404);

        $disk = data_get($snapshot, 'file.storage_disk');
        $path = data_get($snapshot, 'file.storage_path');
        $sha256 = data_get($snapshot, 'file.sha256');
        abort_unless(is_string($disk) && is_string($path), 404);
        $version->loadMissing('resource');
        abort_unless($version->resource
            && (int) $version->resource->id === (int) ($snapshot['resource_id'] ?? 0)
            && (int) $version->resource->organization_id === (int) $revision->organization_id, 404);
        abort_unless($version->storage_disk === $disk && $version->storage_path === $path, 404);
        abort_unless(! $sha256 || hash_equals((string) $sha256, (string) $version->sha256), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $inline = str_starts_with((string) $version->mime_type, 'image/');

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => $version->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.basename($path).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function answer(Request $request, Revision $revision, RevisionAttempt $attempt, int $item, RevisionGraderService $grader, RevisionAttemptSnapshotService $snapshots): JsonResponse|RedirectResponse
    {
        $this->ensureAttempt($request, $revision, $attempt);
        $attempt = $snapshots->ensure($attempt, $revision);
        $itemSnapshot = $snapshots->item($attempt, $item);
        abort_unless(is_array($itemSnapshot), 404);
        $data = $request->validate(['answer' => ['nullable'], 'response_time_seconds' => ['nullable', 'integer', 'min:0', 'max:86400']]);
        $answer = $data['answer'] ?? null;
        $grade = $grader->gradeSnapshot($itemSnapshot, $answer);
        DB::transaction(function () use ($attempt, $item, $itemSnapshot, $answer, $data, $grade) {
            $lockedAttempt = RevisionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            abort_unless($lockedAttempt->status === 'in_progress', 409);
            $response = RevisionResponse::query()->where('revision_attempt_id', $attempt->id)
                ->where('snapshot_item_key', $item)->lockForUpdate()->first()
                ?? new RevisionResponse(['revision_attempt_id' => $attempt->id, 'snapshot_item_key' => $item]);
            if (! $response->exists) {
                $response->revision_item_id = RevisionItem::query()->whereKey($item)->where('revision_id', $lockedAttempt->revision_id)->value('id');
                $response->item_snapshot = $itemSnapshot;
            }
            $response->fill(['answer' => is_array($answer) ? $answer : ['value' => $answer], 'is_correct' => $grade['is_correct'], 'points_awarded' => $grade['points_awarded'],
                'feedback' => $grade['feedback'], 'response_time_seconds' => $data['response_time_seconds'] ?? null, 'answered_at' => now()])->save();
            $lockedAttempt->update(['last_activity_at' => now(), 'current_position' => $lockedAttempt->responses()->count()]);
        });
        $payload = ['saved' => true, 'is_correct' => $grade['is_correct'], 'points_awarded' => $grade['points_awarded']];
        if (data_get($attempt->content_snapshot, 'revision.feedback_mode') === 'immediate') {
            $payload['feedback'] = $grade['feedback'];
        }

        return $request->expectsJson() ? response()->json($payload) : back()->with('status', 'Resposta salva.');
    }

    public function complete(Request $request, Revision $revision, RevisionAttempt $attempt, GamificationService $gamification, RevisionAttemptSnapshotService $snapshots): RedirectResponse
    {
        $this->ensureAttemptOwnership($request, $revision, $attempt);
        $attempt = $snapshots->ensure($attempt, $revision);
        $shouldReward = DB::transaction(function () use ($request, $attempt, $revision, $snapshots): bool {
            $lockedRevision = Revision::query()->lockForUpdate()->findOrFail($revision->id);
            $lockedAttempt = RevisionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($lockedAttempt->status === 'completed') {
                return (bool) data_get($lockedAttempt->content_snapshot, 'revision.gamification_enabled', false)
                    && ! $lockedAttempt->rewarded_at;
            }
            $this->ensureAvailable($request, $lockedRevision);
            abort_unless($lockedAttempt->status === 'in_progress', 409);
            $requiredItems = $snapshots->items($lockedAttempt)
                ->reject(fn (array $item): bool => in_array($item['type'] ?? null, ['explanation', 'example'], true));
            $requiredKeys = $requiredItems->pluck('id')->map(fn ($id): int => (int) $id);
            $required = $requiredKeys->count();
            $answered = $lockedAttempt->responses()->whereIn('snapshot_item_key', $requiredKeys)->count();
            if ($answered < $required) {
                throw ValidationException::withMessages(['attempt' => "Responda todos os itens antes de concluir ({$answered}/{$required})."]);
            }
            $total = (float) $requiredItems->sum(fn (array $item): float => (float) ($item['points'] ?? 0));
            $earned = (float) $lockedAttempt->responses()->whereIn('snapshot_item_key', $requiredKeys)->sum('points_awarded');
            $lockedAttempt->update(['status' => 'completed', 'score' => $total > 0 ? round(($earned / $total) * 10, 2) : 10, 'total_points' => $total, 'completed_at' => now(), 'last_activity_at' => now()]);

            return (bool) data_get($lockedAttempt->content_snapshot, 'revision.gamification_enabled', false);
        }, 3);
        if ($shouldReward) {
            $gamification->reward($attempt->fresh());
        }

        return redirect()->route('student.revisions.result', [$revision, $attempt])->with('status', 'Revisão concluída!');
    }

    public function result(Request $request, Revision $revision, RevisionAttempt $attempt, RevisionAttemptSnapshotService $snapshots): View
    {
        $this->ensureAttemptOwnership($request, $revision, $attempt);
        abort_unless($attempt->status === 'completed', 404);
        $attempt = $snapshots->ensure($attempt, $revision);
        $attempt->load(['responses.item']);
        $revisionSnapshot = data_get($attempt->content_snapshot, 'revision', []);
        $revision->forceFill([
            'title' => $revisionSnapshot['title'] ?? $revision->title,
            'feedback_mode' => $revisionSnapshot['feedback_mode'] ?? $revision->feedback_mode,
        ]);
        $profile = StudentGamificationProfile::query()->where('organization_id', $request->user()->organization_id)
            ->where('student_id', $request->user()->id)->first();

        return view('student.revisions.result', compact('revision', 'attempt', 'profile'));
    }

    private function ensureAvailable(Request $request, Revision $revision): void
    {
        $classIds = $request->user()->schoolClasses()->pluck('school_classes.id');
        abort_unless($revision->organization_id === $request->user()->organization_id && $revision->status === 'published' &&
            $revision->schoolClasses()->whereIn('school_classes.id', $classIds)->exists()
            && (! $revision->available_at || $revision->available_at->lte(now()))
            && (! $revision->due_at || $revision->due_at->gte(now())), 404);
    }

    private function ensureAttempt(Request $request, Revision $revision, RevisionAttempt $attempt): void
    {
        $this->ensureAttemptOwnership($request, $revision, $attempt);
        $this->ensureAvailable($request, $revision);
        abort_unless($attempt->status === 'in_progress', 403);
    }

    private function ensureAttemptOwnership(Request $request, Revision $revision, RevisionAttempt $attempt): void
    {
        abort_unless((int) $revision->organization_id === (int) $request->user()->organization_id
            && (int) $attempt->organization_id === (int) $request->user()->organization_id
            && (int) $attempt->revision_id === (int) $revision->id
            && (int) $attempt->student_id === (int) $request->user()->id, 404);
    }
}
