<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CourtesyGrant;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\MonthlyUsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourtesyController extends Controller
{
    public function index(Request $request): View
    {
        $grants = CourtesyGrant::query()
            ->with(['benefits.plan:id,name,tier_level', 'authorizer:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(25)->withQueryString();

        return view('admin.courtesies.index', compact('grants'));
    }

    public function create(): View
    {
        return view('admin.courtesies.create', $this->options());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $grant = DB::transaction(function () use ($request, $data): CourtesyGrant {
            $benefits = $data['benefits'];
            unset($data['benefits']);
            $data['authorized_by'] = $request->user()->id;
            $data['status'] = now()->lt($data['starts_at']) ? 'scheduled' : 'active';
            $data['metadata'] = $data['target_scope'] === 'organization'
                ? ['eligible_roles' => ['teacher']]
                : [];

            $grant = CourtesyGrant::query()->create($data);
            $grant->benefits()->createMany($benefits);

            return $grant;
        });

        AuditLog::log('courtesy_created', CourtesyGrant::class, $grant->id, [
            'target_scope' => $grant->target_scope,
            'target_id' => $grant->target_id,
            'target_role' => $grant->target_role,
            'starts_at' => $grant->starts_at,
            'ends_at' => $grant->ends_at,
            'reason' => $grant->reason,
            'benefits' => $grant->benefits()->get()->toArray(),
        ]);

        return redirect()->route('admin.courtesies.index')->with('status', 'Cortesia concedida com sucesso.');
    }

    public function edit(CourtesyGrant $courtesy): View
    {
        abort_if(in_array($courtesy->status, ['cancelled', 'expired'], true), 422, 'Cortesia encerrada não pode ser editada.');
        $courtesy->load('benefits');

        return view('admin.courtesies.edit', ['courtesy' => $courtesy, ...$this->options()]);
    }

    public function update(Request $request, CourtesyGrant $courtesy): RedirectResponse
    {
        abort_if(in_array($courtesy->status, ['cancelled', 'expired'], true), 422, 'Cortesia encerrada não pode ser editada.');
        $before = $courtesy->load('benefits')->toArray();
        $data = $this->validated($request);

        DB::transaction(function () use ($courtesy, $data): void {
            $benefits = $data['benefits'];
            unset($data['benefits']);
            $data['metadata'] = $data['target_scope'] === 'organization'
                ? ['eligible_roles' => ['teacher']]
                : [];
            if ($courtesy->status !== 'suspended') {
                $data['status'] = now()->lt($data['starts_at']) ? 'scheduled' : 'active';
            }
            $courtesy->update($data);
            $courtesy->benefits()->delete();
            $courtesy->benefits()->createMany($benefits);
        });

        AuditLog::log('courtesy_updated', CourtesyGrant::class, $courtesy->id, [
            'before' => $before,
            'after' => $courtesy->fresh('benefits')->toArray(),
        ]);

        return redirect()->route('admin.courtesies.index')->with('status', 'Cortesia atualizada.');
    }

    public function suspend(Request $request, CourtesyGrant $courtesy): RedirectResponse
    {
        abort_unless(in_array($courtesy->status, ['active', 'scheduled'], true), 422);
        $courtesy->update(['status' => 'suspended', 'suspended_at' => now(), 'suspended_by' => $request->user()->id]);
        AuditLog::log('courtesy_suspended', CourtesyGrant::class, $courtesy->id);

        return back()->with('status', 'Cortesia suspensa; a data final continua vigente.');
    }

    public function activate(Request $request, CourtesyGrant $courtesy): RedirectResponse
    {
        abort_unless($courtesy->status === 'suspended' && $courtesy->ends_at->isFuture(), 422);
        $courtesy->update([
            'status' => $courtesy->starts_at->isFuture() ? 'scheduled' : 'active',
            'suspended_at' => null,
            'suspended_by' => null,
        ]);
        AuditLog::log('courtesy_reactivated', CourtesyGrant::class, $courtesy->id, ['reactivated_by' => $request->user()->id]);

        return back()->with('status', 'Cortesia reativada.');
    }

    public function cancel(Request $request, CourtesyGrant $courtesy): RedirectResponse
    {
        abort_if(in_array($courtesy->status, ['cancelled', 'expired'], true), 422);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:2000']]);
        $courtesy->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $request->user()->id,
            'metadata' => [...($courtesy->metadata ?? []), 'cancellation_reason' => $data['reason']],
        ]);
        AuditLog::log('courtesy_cancelled', CourtesyGrant::class, $courtesy->id, ['reason' => $data['reason']]);

        return back()->with('status', 'Cortesia cancelada definitivamente.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'target_scope' => ['required', Rule::in(['all', 'user', 'role', 'organization'])],
            'target_id' => ['nullable', 'integer', 'required_if:target_scope,user,organization'],
            'target_role' => ['nullable', Rule::in(['global_admin', 'institution_admin', 'teacher', 'student', 'guardian']), 'required_if:target_scope,role'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'benefits' => ['required', 'array', 'min:1', 'max:20'],
            'benefits.*.benefit_type' => ['required', Rule::in(['plan', 'credit', 'replace', 'unlimited', 'feature'])],
            'benefits.*.resource_key' => ['nullable', Rule::in(MonthlyUsageService::RESOURCES)],
            'benefits.*.quantity' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'benefits.*.plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'benefits.*.feature_key' => ['nullable', 'string', 'max:80'],
        ]);

        if ($data['target_scope'] === 'user' && ! User::query()->whereKey($data['target_id'])->exists()) {
            throw ValidationException::withMessages(['target_id' => 'Usuário não encontrado.']);
        }
        if ($data['target_scope'] === 'organization' && ! Organization::query()->whereKey($data['target_id'])->exists()) {
            throw ValidationException::withMessages(['target_id' => 'Instituição não encontrada.']);
        }

        foreach ($data['benefits'] as $index => $benefit) {
            $type = $benefit['benefit_type'];
            if ($type === 'plan' && empty($benefit['plan_id'])) {
                throw ValidationException::withMessages(["benefits.{$index}.plan_id" => 'Selecione o plano gratuito.']);
            }
            if (in_array($type, ['credit', 'replace'], true) && (empty($benefit['resource_key']) || empty($benefit['quantity']))) {
                throw ValidationException::withMessages(["benefits.{$index}.quantity" => 'Informe recurso e quantidade.']);
            }
            if ($type === 'unlimited' && empty($benefit['resource_key'])) {
                throw ValidationException::withMessages(["benefits.{$index}.resource_key" => 'Informe o recurso ilimitado.']);
            }
            if ($type === 'feature' && empty($benefit['feature_key'])) {
                throw ValidationException::withMessages(["benefits.{$index}.feature_key" => 'Informe a funcionalidade.']);
            }
        }

        return $data;
    }

    private function options(): array
    {
        return [
            'plans' => Plan::query()->where('status', 'active')->orderBy('tier_level')->get(['id', 'name', 'tier_level']),
            'users' => User::query()->where('status', 'active')->orderBy('name')->limit(500)->get(['id', 'name', 'email', 'type']),
            'organizations' => Organization::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
            'resources' => [
                MonthlyUsageService::OMR_SCANS => 'Leituras OMR',
                MonthlyUsageService::EXAM_PUBLICATIONS => 'Provas publicadas',
                MonthlyUsageService::QUESTIONS_CREATED => 'Questões cadastradas',
            ],
            'features' => ['omr' => 'OMR', 'export_pdf' => 'Exportação PDF', 'sharing' => 'Compartilhamento', 'certificates' => 'Certificados'],
        ];
    }
}
