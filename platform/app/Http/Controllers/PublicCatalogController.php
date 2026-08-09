<?php

namespace App\Http\Controllers;

use App\Models\PublicCatalogSubmission;
use App\Services\PublicCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicCatalogController extends Controller
{
    public function index(Request $request, PublicCatalogService $catalog): View
    {
        $submissions = PublicCatalogSubmission::query()
            ->where('submitter_id', $request->user()->id)
            ->where('organization_id', $request->user()->organization_id)
            ->with(['submittable', 'entry', 'events.actor:id,name'])
            ->latest('submitted_at')
            ->paginate(15)
            ->withQueryString();

        return view('public-catalog.index', [
            'submissions' => $submissions,
            'reputation' => $catalog->reputation($request->user()),
        ]);
    }

    public function createSubmission(Request $request, PublicCatalogService $catalog): View
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(PublicCatalogService::TARGET_TYPES)],
            'id' => ['required', 'integer'],
        ]);
        $target = $catalog->resolveOwnedTarget($request->user(), $data['type'], (int) $data['id']);
        abort_if($target->visibility_scope === 'platform_public', 422);

        return view('public-catalog.submit', [
            'target' => $target,
            'targetType' => $data['type'],
            'idempotencyKey' => (string) Str::uuid(),
            'rightsBases' => PublicCatalogService::RIGHTS_BASES,
            'termsVersion' => config('public_catalog.terms_version'),
        ]);
    }

    public function storeSubmission(Request $request, PublicCatalogService $catalog): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(PublicCatalogService::TARGET_TYPES)],
            'target_id' => ['required', 'integer'],
            'rights_basis' => ['required', Rule::in(PublicCatalogService::RIGHTS_BASES)],
            'rights_notes' => ['nullable', 'string', 'max:5000'],
            'attribution' => ['nullable', 'string', 'max:500'],
            'evidence_url' => ['nullable', 'url:http,https', 'max:2048'],
            'rights_confirmed' => ['accepted'],
            'terms_accepted' => ['accepted'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        if (in_array($data['rights_basis'], ['licensed', 'authorized'], true)
            && blank($data['rights_notes'] ?? null)
            && blank($data['evidence_url'] ?? null)) {
            throw ValidationException::withMessages([
                'rights_notes' => 'Descreva a licença/autorização ou informe uma evidência verificável.',
            ]);
        }
        $target = $catalog->resolveOwnedTarget($request->user(), $data['type'], (int) $data['target_id']);
        $catalog->submit($target, $request->user(), $data);

        return redirect()->route('public-catalog.index')->with('status', 'Conteúdo enviado para moderação.');
    }

    public function withdraw(
        Request $request,
        PublicCatalogSubmission $submission,
        PublicCatalogService $catalog,
    ): RedirectResponse {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $catalog->withdraw($submission, $request->user(), $data['reason']);

        return back()->with('status', 'Submissão retirada da fila de moderação.');
    }

    public function createReport(Request $request, PublicCatalogService $catalog): View
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(PublicCatalogService::TARGET_TYPES)],
            'id' => ['required', 'integer'],
        ]);
        $target = $catalog->resolvePublicTarget($request->user(), $data['type'], (int) $data['id']);
        abort_if((int) $target->owner_id === (int) $request->user()->id, 422);

        return view('public-catalog.report', [
            'target' => $target,
            'targetType' => $data['type'],
            'idempotencyKey' => (string) Str::uuid(),
            'reasons' => PublicCatalogService::REPORT_REASONS,
        ]);
    }

    public function storeReport(Request $request, PublicCatalogService $catalog): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(PublicCatalogService::TARGET_TYPES)],
            'target_id' => ['required', 'integer'],
            'reason_code' => ['required', Rule::in(PublicCatalogService::REPORT_REASONS)],
            'details' => ['required', 'string', 'min:20', 'max:5000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $target = $catalog->resolvePublicTarget($request->user(), $data['type'], (int) $data['target_id']);
        $catalog->report($target, $request->user(), $data);

        return redirect()->route($data['type'] === 'question' ? 'questions.index' : 'question-resources.index', [
            $data['type'] === 'question' ? 'tab' : 'scope' => 'platform',
        ])->with('status', 'Denúncia registrada para análise.');
    }
}
