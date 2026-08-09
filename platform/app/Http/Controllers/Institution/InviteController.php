<?php

namespace App\Http\Controllers\Institution;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Services\InstitutionPermissionService;
use App\Services\InviteManagerService;
use App\Services\UserLinkerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InviteController extends Controller
{
    public function __construct(
        private InviteManagerService $inviteManager,
        private UserLinkerService $linker
    ) {}

    /**
     * Lista convites da organização do admin logado.
     */
    public function index()
    {
        $org = auth()->user()->organization;
        $invites = Invite::where('organization_id', $org->id)
            ->with('inviter')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('institution.invites.index', compact('invites'));
    }

    /**
     * Formulário de envio de convite.
     */
    public function create()
    {
        $user = auth()->user();
        $allowedRoles = app(InstitutionPermissionService::class)
            ->invitationalRoles($user, (int) $user->organization_id);

        return view('institution.invites.create', compact('allowedRoles'));
    }

    /**
     * Envia convite.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        $allowedRoles = app(InstitutionPermissionService::class)
            ->invitationalRoles($user, (int) $user->organization_id);
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'target_role' => ['required', Rule::in($allowedRoles)],
        ]);

        $org = $user->organization;

        $this->inviteManager->send($user, $validated['email'], $validated['target_role'], $org);

        return redirect()->route('institution.invites.index')
            ->with('status', "Convite enviado para {$validated['email']}!");
    }

    /**
     * Cancela um convite pendente.
     */
    public function destroy(Invite $invite)
    {
        abort_unless((int) $invite->organization_id === (int) auth()->user()->organization_id, 404);
        $this->inviteManager->cancel($invite, auth()->user());

        return redirect()->route('institution.invites.index')
            ->with('status', 'Convite cancelado.');
    }

    /**
     * Lista convites recebidos pelo usuário logado.
     */
    public function received()
    {
        $invites = Invite::forEmail(auth()->user()->email)
            ->pending()
            ->notExpired()
            ->with(['inviter', 'organization'])
            ->orderByDesc('created_at')
            ->get();

        return view('institution.invites.received', compact('invites'));
    }

    /**
     * Aceita um convite via token.
     */
    public function accept(string $token)
    {
        $invite = Invite::where('token', $token)->firstOrFail();
        $this->linker->acceptInvite($invite, auth()->user());

        return redirect()->route('dashboard')
            ->with('status', "Vínculo com {$invite->organization->name} aceito com sucesso!");
    }

    /**
     * Recusa um convite via token.
     */
    public function decline(string $token)
    {
        $invite = Invite::where('token', $token)->firstOrFail();
        $this->linker->declineInvite($invite, auth()->user());

        return redirect()->route('institution.invites.received')
            ->with('status', 'Convite recusado.');
    }
}
