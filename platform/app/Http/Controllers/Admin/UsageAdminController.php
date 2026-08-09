<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminBatchOperation;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\UsagePeriod;
use App\Models\User;
use App\Services\AdminAudienceService;
use App\Services\MonthlyUsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UsageAdminController extends Controller
{
    public function __construct(
        private readonly AdminAudienceService $audiences,
        private readonly MonthlyUsageService $usage,
    ) {}

    public function index(Request $request): View
    {
        $periods = UsagePeriod::query()
            ->with(['user:id,name,email,type', 'organization:id,name'])
            ->when($request->filled('resource_key'), fn ($query) => $query->where('resource_key', $request->string('resource_key')))
            ->latest('period_start')->latest('id')->paginate(30)->withQueryString();

        return view('admin.usage.index', [
            'periods' => $periods,
            'resources' => $this->resourceLabels(),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->where('status', 'active')->orderBy('name')->limit(300)->get(['id', 'name', 'email', 'type']),
            'operations' => AdminBatchOperation::query()->with('requester:id,name')->latest()->limit(10)->get(),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $this->validateTarget($request);
        $query = $this->audiences->query($data['target_scope'], $data['target_id'] ?? null, $data['target_role'] ?? null);

        return response()->json([
            'affected_count' => (clone $query)->count(),
            'users' => $query->orderBy('name')->limit(20)->get(['id', 'name', 'email', 'type']),
            'truncated' => (clone $query)->count() > 20,
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $this->validateTarget($request) + $request->validate([
            'resource_keys' => ['required', 'array', 'min:1'],
            'resource_keys.*' => ['required', Rule::in(MonthlyUsageService::RESOURCES)],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'confirmation' => ['required', 'in:REDEFINIR LIMITES'],
        ]);
        $actor = $request->user();
        $users = $this->audiences->query($data['target_scope'], $data['target_id'] ?? null, $data['target_role'] ?? null)->get();

        $operation = AdminBatchOperation::query()->create([
            'operation_type' => 'usage_reset',
            'target_scope' => $data['target_scope'],
            'target_id' => $data['target_id'] ?? null,
            'target_role' => $data['target_role'] ?? null,
            'resource_keys' => $data['resource_keys'],
            'status' => 'processing',
            'selected_count' => $users->count(),
            'requested_by' => $actor->id,
            'reason' => $data['reason'],
        ]);

        $processed = 0;
        $failed = [];
        foreach ($users as $user) {
            foreach ($data['resource_keys'] as $resource) {
                try {
                    $this->usage->reset(
                        $user,
                        $resource,
                        $actor,
                        $data['reason'],
                        "admin-reset:{$operation->id}:{$user->id}:{$resource}",
                    );
                } catch (\Throwable $exception) {
                    $failed[] = ['user_id' => $user->id, 'resource' => $resource, 'error' => $exception->getMessage()];
                }
            }
            $processed++;
        }

        $operation->update([
            'status' => $failed === [] ? 'completed' : 'completed_with_errors',
            'processed_count' => $processed,
            'failed_count' => count($failed),
            'result' => ['failures' => array_slice($failed, 0, 100)],
            'completed_at' => now(),
        ]);

        AuditLog::log('monthly_limits_reset', AdminBatchOperation::class, $operation->id, [
            'selected_count' => $users->count(),
            'resource_keys' => $data['resource_keys'],
            'failed_count' => count($failed),
            'reason' => $data['reason'],
        ]);

        return back()->with('status', "Limites redefinidos para {$processed} usuário(s).");
    }

    private function validateTarget(Request $request): array
    {
        return $request->validate([
            'target_scope' => ['required', Rule::in(['all', 'user', 'role', 'organization'])],
            'target_id' => ['nullable', 'integer', 'required_if:target_scope,user,organization'],
            'target_role' => ['nullable', 'string', Rule::in(['global_admin', 'institution_admin', 'teacher', 'student', 'guardian']), 'required_if:target_scope,role'],
        ]);
    }

    private function resourceLabels(): array
    {
        return [
            MonthlyUsageService::OMR_SCANS => 'Leituras OMR',
            MonthlyUsageService::EXAM_PUBLICATIONS => 'Provas publicadas',
            MonthlyUsageService::QUESTIONS_CREATED => 'Questões cadastradas',
        ];
    }
}
