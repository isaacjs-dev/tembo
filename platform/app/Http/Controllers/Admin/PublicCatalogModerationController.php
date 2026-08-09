<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicCatalogReport;
use App\Models\PublicCatalogSubmission;
use App\Services\PublicCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicCatalogModerationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: 'pending';
        $submissions = PublicCatalogSubmission::query()
            ->with(['submitter:id,name,email', 'submittable', 'entry'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->oldest('submitted_at')
            ->paginate(20, ['*'], 'submissions_page')
            ->withQueryString();
        $reports = PublicCatalogReport::query()
            ->with(['reporter:id,name,email', 'entry.entryable', 'entry.submission'])
            ->whereIn('status', ['open', 'in_review'])
            ->oldest()
            ->paginate(20, ['*'], 'reports_page')
            ->withQueryString();

        return view('admin.public-catalog.index', compact('submissions', 'reports', 'status'));
    }

    public function show(PublicCatalogSubmission $submission): View
    {
        $submission->load(['submitter:id,name,email', 'moderator:id,name', 'submittable', 'entry', 'events.actor:id,name']);

        return view('admin.public-catalog.show', compact('submission'));
    }

    public function start(
        Request $request,
        PublicCatalogSubmission $submission,
        PublicCatalogService $catalog,
    ): RedirectResponse {
        $catalog->startReview($submission, $request->user());

        return back()->with('status', 'Análise iniciada e atribuída a você.');
    }

    public function decide(
        Request $request,
        PublicCatalogSubmission $submission,
        PublicCatalogService $catalog,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'duplicate_of_submission_id' => ['nullable', 'integer'],
        ]);
        $catalog->decide(
            $submission,
            $request->user(),
            $data['decision'],
            $data['reason'],
            isset($data['duplicate_of_submission_id']) ? (int) $data['duplicate_of_submission_id'] : null,
        );

        return redirect()->route('admin.public-catalog.index')->with('status', 'Decisão de moderação registrada.');
    }

    public function resolveReport(
        Request $request,
        PublicCatalogReport $report,
        PublicCatalogService $catalog,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['upheld', 'dismissed'])],
            'resolution' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
        $catalog->resolveReport($report, $request->user(), $data['decision'], $data['resolution']);

        return back()->with('status', 'Denúncia resolvida e auditada.');
    }

    public function evidence(PublicCatalogSubmission $submission): StreamedResponse
    {
        abort_unless(data_get($submission->snapshot_json, 'kind') === 'resource', 404);
        $version = data_get($submission->snapshot_json, 'version', []);
        $disk = $version['storage_disk'] ?? null;
        $path = $version['storage_path'] ?? null;
        abort_unless($disk && $path && Storage::disk($disk)->exists($path), 404);
        if (filled($version['sha256'] ?? null)) {
            $actual = hash('sha256', Storage::disk($disk)->get($path));
            abort_unless(hash_equals($version['sha256'], $actual), 409);
        }

        return Storage::disk($disk)->download($path, basename($path), [
            'Content-Type' => $version['mime_type'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
