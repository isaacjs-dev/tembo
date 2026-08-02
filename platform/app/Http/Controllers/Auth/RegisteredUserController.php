<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($request): User {
            $organizationName = trim((string) $request->input('organization_name'));
            if ($organizationName === '') {
                $organizationName = 'Espaço de '.$request->string('name')->trim();
            }

            $organization = Organization::create([
                'name' => $organizationName,
                'subdomain' => $this->availableSubdomain($organizationName),
                'active' => true,
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'name' => $request->name,
                'email' => mb_strtolower($request->email),
                'password' => Hash::make($request->password),
                'type' => 'institution_admin',
                'status' => 'active',
                'settings' => ['requires_email_verification' => true],
            ]);

            Role::findOrCreate('institution_admin', 'web');
            $user->assignRole('institution_admin');
            $organization->update(['owner_user_id' => $user->id]);
            $user->organizations()->syncWithoutDetaching([
                $organization->id => [
                    'role_in_org' => 'admin',
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            ]);

            if ($plan = Plan::query()->where('slug', 'start')->where('status', 'active')->first()) {
                Subscription::create([
                    'organization_id' => $organization->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    private function availableSubdomain(string $name): string
    {
        $base = Str::slug($name) ?: 'instituicao';
        $base = Str::limit($base, 42, '');
        $candidate = $base;
        $suffix = 1;

        while (Organization::withTrashed()->where('subdomain', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
