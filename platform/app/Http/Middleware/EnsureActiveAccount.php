<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $inactiveAccount = $user->status !== 'active';
        $inactiveOrganization = $user->type !== 'global_admin'
            && $user->organization_id !== null
            && (! $user->organization || ! $user->organization->active);

        if (! $inactiveAccount && ! $inactiveOrganization) {
            return $next($request);
        }

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $message = $inactiveOrganization
            ? 'O acesso da instituição está inativo. Procure a administração.'
            : 'Sua conta está inativa. Procure a administração.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
