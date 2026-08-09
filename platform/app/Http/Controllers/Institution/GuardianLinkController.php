<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\GuardianStudentLink;
use App\Models\User;
use App\Rules\ActiveOrganizationMember;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class GuardianLinkController extends Controller
{
    public function index(Request $request): View
    {
        $organizationId = (int) $request->user()->organization_id;
        abort_unless($organizationId > 0, 403);

        $links = GuardianStudentLink::query()
            ->where('organization_id', $organizationId)
            ->with(['guardian:id,name,email,status', 'student:id,name,email,status'])
            ->latest()
            ->paginate(20);

        $students = User::query()
            ->memberOfOrganization($organizationId, 'student')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('institution.guardians.index', compact('links', 'students'));
    }

    public function store(Request $request): RedirectResponse
    {
        $organizationId = (int) $request->user()->organization_id;
        abort_unless($organizationId > 0, 403);

        $validated = $request->validate([
            'student_id' => [
                'required',
                'integer',
                new ActiveOrganizationMember($organizationId, 'student'),
            ],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_email' => ['required', 'email:rfc', 'max:255'],
            'guardian_password' => ['nullable', 'string', 'min:12', 'confirmed'],
            'relationship' => ['required', 'string', 'max:60'],
        ]);

        $createdGuardian = null;

        DB::transaction(function () use ($validated, $organizationId, $request, &$createdGuardian): void {
            $guardian = User::withTrashed()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($validated['guardian_email'])])
                ->lockForUpdate()
                ->first();

            if ($guardian) {
                $membership = $guardian->organizations()->whereKey($organizationId)->first();
                if ($guardian->trashed()
                    || $guardian->status !== 'active'
                    || ($membership && ($membership->pivot->status !== 'active' || $membership->pivot->role_in_org !== 'guardian'))) {
                    throw ValidationException::withMessages([
                        'guardian_email' => 'Este e-mail já pertence a uma conta incompatível com este vínculo.',
                    ]);
                }
            } else {
                if (blank($validated['guardian_name']) || blank($validated['guardian_password'] ?? null)) {
                    throw ValidationException::withMessages([
                        'guardian_name' => 'Informe nome e senha provisória para criar a conta do responsável.',
                        'guardian_password' => 'A senha provisória deve ter pelo menos 12 caracteres.',
                    ]);
                }

                $guardian = User::create([
                    'organization_id' => $organizationId,
                    'name' => $validated['guardian_name'],
                    'email' => mb_strtolower($validated['guardian_email']),
                    'password' => Hash::make($validated['guardian_password']),
                    'type' => 'guardian',
                    'status' => 'active',
                    'settings' => [
                        'requires_email_verification' => true,
                        'must_change_password' => true,
                    ],
                ]);
                Role::findOrCreate('guardian', 'web');
                $guardian->assignRole('guardian');
                $createdGuardian = $guardian;
            }

            $guardian->organizations()->syncWithoutDetaching([
                $organizationId => [
                    'role_in_org' => 'guardian',
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            ]);

            if (! $guardian->hasVerifiedEmail()) {
                $settings = is_array($guardian->settings) ? $guardian->settings : [];
                $guardian->update([
                    'settings' => array_merge($settings, ['requires_email_verification' => true]),
                ]);
            }

            $link = GuardianStudentLink::withTrashed()->firstOrNew([
                'organization_id' => $organizationId,
                'guardian_id' => $guardian->id,
                'student_id' => (int) $validated['student_id'],
            ]);

            $link->fill([
                'created_by' => $request->user()->id,
                'relationship' => $validated['relationship'],
            ]);
            $link->save();

            if ($link->trashed()) {
                $link->restore();
            }
        });

        if ($createdGuardian) {
            event(new Registered($createdGuardian));
        }

        return back()->with('status', 'Responsável vinculado ao estudante com sucesso.');
    }

    public function destroy(Request $request, GuardianStudentLink $guardianLink): RedirectResponse
    {
        abort_unless(
            (int) $guardianLink->organization_id === (int) $request->user()->organization_id,
            404
        );

        $guardianLink->delete();

        return back()->with('status', 'Vínculo removido. A conta do responsável foi preservada.');
    }
}
