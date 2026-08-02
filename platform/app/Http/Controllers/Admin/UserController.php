<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $users = User::with('organization')
            ->when($search, function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->latest()
            ->paginate(15);

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        $organizations = Organization::orderBy('name')->get();
        $plans = Plan::active()->orderBy('name')->get();

        return view('admin.users.create', compact('organizations', 'plans'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedUser($request);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'password' => Hash::make($validated['password']),
                'type' => $validated['type'],
                'organization_id' => $validated['organization_id'] ?? null,
                'status' => 'active',
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            Role::findOrCreate($validated['type'], 'web');
            $user->assignRole($validated['type']);
            $this->syncPlan($user, $validated['plan_id'] ?? null);

            return $user;
        });

        return redirect()
            ->route('admin.users.edit', $user)
            ->with('status', 'Usuário criado com sucesso.');
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.edit', $user);
    }

    public function edit(User $user)
    {
        $organizations = Organization::orderBy('name')->get();
        $plans = Plan::active()->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'organizations', 'plans'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $this->validatedUser($request, $user);

        if ($user->is($request->user()) && $validated['type'] !== 'global_admin') {
            return back()->withErrors([
                'type' => 'Você não pode remover o próprio acesso de administrador global.',
            ]);
        }

        DB::transaction(function () use ($validated, $user): void {
            $data = [
                'name' => $validated['name'],
                'email' => mb_strtolower($validated['email']),
                'type' => $validated['type'],
                'organization_id' => $validated['organization_id'] ?? null,
            ];

            if (! empty($validated['password'])) {
                $data['password'] = Hash::make($validated['password']);
            }

            $user->update($data);
            Role::findOrCreate($validated['type'], 'web');
            $user->syncRoles([$validated['type']]);
            $this->syncPlan($user, $validated['plan_id'] ?? null);
        });

        return redirect()->route('admin.users.index')->with('status', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Você não pode excluir a si mesmo.');
        }

        $user->delete();

        return back()->with('status', 'Usuário movido para a lixeira com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedUser(Request $request, ?User $user = null): array
    {
        $userId = $user?->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'type' => [
                'required',
                'string',
                Rule::in(['global_admin', 'institution_admin', 'teacher', 'student', 'guardian']),
            ],
            'organization_id' => [
                Rule::requiredIf(fn () => $request->input('type') !== 'global_admin'),
                'nullable',
                'exists:organizations,id',
            ],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'password' => [
                $user ? 'nullable' : 'required',
                'string',
                'min:12',
                'confirmed',
            ],
        ]);
    }

    private function syncPlan(User $user, mixed $planId): void
    {
        $activeSubscription = $user->subscription;

        if (! $planId) {
            $activeSubscription?->update(['status' => 'inactive']);

            return;
        }

        if ($activeSubscription && (int) $activeSubscription->plan_id === (int) $planId) {
            return;
        }

        $activeSubscription?->update(['status' => 'inactive']);
        $user->subscription()->create([
            'plan_id' => (int) $planId,
            'status' => 'active',
            'organization_id' => $user->organization_id,
            'starts_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }
}
