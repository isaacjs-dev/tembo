<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\User;
use App\Services\UserLinkerService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class InviteActivationController extends Controller
{
    public function show(string $token): View
    {
        $invite = $this->pendingInvite($token);
        $existingAccount = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($invite->invitee_email)])
            ->exists();

        return view('auth.invite-activation', compact('invite', 'existingAccount'));
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $invite = $this->pendingInvite($token);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($invite, $validated): User {
            $lockedInvite = Invite::query()->whereKey($invite->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedInvite->isPending() && ! $lockedInvite->isExpired(), 410);

            $email = mb_strtolower($lockedInvite->invitee_email);
            abort_if(User::withTrashed()->whereRaw('LOWER(email) = ?', [$email])->exists(), 409);

            $accountType = $lockedInvite->target_role === 'student' ? 'student' : 'teacher';
            $user = User::create([
                'organization_id' => null,
                'name' => $validated['name'],
                'email' => $email,
                'password' => Hash::make($validated['password']),
                'type' => $accountType,
                'status' => 'active',
                'settings' => ['requires_email_verification' => true],
            ]);
            Role::findOrCreate($accountType, 'web');
            $user->assignRole($accountType);

            Auth::login($user);
            app(UserLinkerService::class)->acceptInvite($lockedInvite, $user);

            return $user;
        });

        event(new Registered($user));

        return redirect()->route('verification.notice')
            ->with('status', 'Conta ativada. Confirme seu e-mail para acessar o workspace.');
    }

    private function pendingInvite(string $token): Invite
    {
        return Invite::query()
            ->where('token', $token)
            ->pending()
            ->notExpired()
            ->with('organization:id,name')
            ->firstOrFail();
    }
}
