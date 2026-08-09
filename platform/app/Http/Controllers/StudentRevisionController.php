<?php

namespace App\Http\Controllers;

use App\Models\QuestionResourceVersion;
use App\Models\Revision;
use App\Models\RevisionAttempt;
use App\Models\RevisionItem;
use App\Models\RevisionResponse;
use App\Services\GamificationService;
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
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))->with(['discipline', 'schoolClasses'])
            ->withCount('activeItems')->latest('published_at')->paginate(12);
        $attempts = RevisionAttempt::where('student_id', $request->user()->id)->whereIn('revision_id', $revisions->pluck('id'))->latest()->get()->unique('revision_id')->keyBy('revision_id');
        $profile = $request->user()->gamificationProfile;

        return view('student.revisions.index', compact('revisions', 'attempts', 'profile'));
    }

    public function show(Request $request, Revision $revision): View
    {
        $this->ensureAvailable($request, $revision);
        $revision->loadCount('activeItems');
        $attempts = $revision->attempts()->where('student_id', $request->user()->id)->latest('attempt_number')->get();

        return view('student.revisions.show', compact('revision', 'attempts'));
    }

    public function start(Request $request, Revision $revision): RedirectResponse
    {
        $this->ensureAvailable($request, $revision);
        $attempt = DB::transaction(function () use ($request, $revision) {
            DB::table('users')->where('id', $request->user()->id)->lockForUpdate()->first();
            $attempts = $revision->attempts()->where('student_id', $request->user()->id)->lockForUpdate()->get();
            if ($current = $attempts->firstWhere('status', 'in_progress')) {
                return $current;
            }
            if ($attempts->count() >= $revision->max_attempts) {
                throw ValidationException::withMessages(['attempt' => 'Você já usou todas as tentativas desta revisão.']);
            }

            return $revision->attempts()->create(['student_id' => $request->user()->id, 'organization_id' => $request->user()->organization_id,
                'attempt_number' => ((int) $attempts->max('attempt_number')) + 1, 'status' => 'in_progress', 'started_at' => now(), 'last_activity_at' => now()]);
        }, 3);

        return redirect()->route('student.revisions.execute', [$revision, $attempt]);
    }

    public function execute(Request $request, Revision $revision, RevisionAttempt $attempt): View
    {
        $this->ensureAttempt($request, $revision, $attempt);
        $revision->load('activeItems');
        $attempt->load('responses');

        return view('student.revisions.execute', compact('revision', 'attempt'));
    }

    public function resource(
        Request $request,
        Revision $revision,
        RevisionAttempt $attempt,
        RevisionItem $item,
        QuestionResourceVersion $version,
    ): StreamedResponse {
        $this->ensureAttempt($request, $revision, $attempt);
        abort_unless((int) $item->revision_id === (int) $revision->id && $item->is_active, 404);

        $snapshot = collect($item->content['resources'] ?? [])
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

    public function answer(Request $request, Revision $revision, RevisionAttempt $attempt, RevisionItem $item, RevisionGraderService $grader): JsonResponse|RedirectResponse
    {
        $this->ensureAttempt($request, $revision, $attempt);
        abort_unless((int) $item->revision_id === (int) $revision->id && $item->is_active, 404);
        $data = $request->validate(['answer' => ['nullable'], 'response_time_seconds' => ['nullable', 'integer', 'min:0', 'max:86400']]);
        $answer = $data['answer'] ?? null;
        $grade = $grader->grade($item, $answer);
        DB::transaction(function () use ($attempt, $item, $answer, $data, $grade, $grader) {
            $response = RevisionResponse::firstOrNew(['revision_attempt_id' => $attempt->id, 'revision_item_id' => $item->id]);
            if (! $response->exists) {
                $response->item_snapshot = $grader->snapshot($item);
            }
            $response->fill(['answer' => is_array($answer) ? $answer : ['value' => $answer], 'is_correct' => $grade['is_correct'], 'points_awarded' => $grade['points_awarded'],
                'feedback' => $grade['feedback'], 'response_time_seconds' => $data['response_time_seconds'] ?? null, 'answered_at' => now()])->save();
            $attempt->update(['last_activity_at' => now(), 'current_position' => $attempt->responses()->count()]);
        });
        $payload = ['saved' => true, 'is_correct' => $grade['is_correct'], 'points_awarded' => $grade['points_awarded']];
        if ($revision->feedback_mode === 'immediate') {
            $payload['feedback'] = $grade['feedback'];
        }

        return $request->expectsJson() ? response()->json($payload) : back()->with('status', 'Resposta salva.');
    }

    public function complete(Request $request, Revision $revision, RevisionAttempt $attempt, GamificationService $gamification): RedirectResponse
    {
        $this->ensureAttempt($request, $revision, $attempt);
        $required = $revision->activeItems()->whereNotIn('type', ['explanation', 'example'])->count();
        $answered = $attempt->responses()->whereHas('item', fn ($q) => $q->where('is_active', true)->whereNotIn('type', ['explanation', 'example']))->count();
        if ($answered < $required) {
            throw ValidationException::withMessages(['attempt' => "Responda todos os itens antes de concluir ({$answered}/{$required})."]);
        }
        DB::transaction(function () use ($attempt, $revision, $gamification) {
            $total = (float) $revision->activeItems()->whereNotIn('type', ['explanation', 'example'])->sum('points');
            $earned = (float) $attempt->responses()->sum('points_awarded');
            $attempt->update(['status' => 'completed', 'score' => $total > 0 ? round(($earned / $total) * 10, 2) : 10, 'total_points' => $total, 'completed_at' => now(), 'last_activity_at' => now()]);
            if ($revision->gamification_enabled) {
                $gamification->reward($attempt->fresh());
            }
        });

        return redirect()->route('student.revisions.result', [$revision, $attempt])->with('status', 'Revisão concluída!');
    }

    public function result(Request $request, Revision $revision, RevisionAttempt $attempt): View
    {
        abort_unless((int) $attempt->revision_id === (int) $revision->id && (int) $attempt->student_id === (int) $request->user()->id, 403);
        abort_unless($attempt->status === 'completed', 404);
        $attempt->load(['responses.item']);
        $profile = $request->user()->gamificationProfile;

        return view('student.revisions.result', compact('revision', 'attempt', 'profile'));
    }

    private function ensureAvailable(Request $request, Revision $revision): void
    {
        $classIds = $request->user()->schoolClasses()->pluck('school_classes.id');
        abort_unless($revision->organization_id === $request->user()->organization_id && $revision->status === 'published' &&
            $revision->schoolClasses()->whereIn('school_classes.id', $classIds)->exists() && (! $revision->available_at || $revision->available_at->lte(now())), 404);
    }

    private function ensureAttempt(Request $request, Revision $revision, RevisionAttempt $attempt): void
    {
        $this->ensureAvailable($request, $revision);
        abort_unless((int) $attempt->revision_id === (int) $revision->id && (int) $attempt->student_id === (int) $request->user()->id && $attempt->status === 'in_progress', 403);
    }
}
