<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = is_array($request->user()?->settings)
            ? $request->user()->settings
            : [];

        if (
            (bool) ($settings['must_change_password'] ?? false)
            && ! $request->routeIs('profile.*')
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Altere sua senha provisória antes de continuar.',
                ], 403);
            }

            return redirect()
                ->route('profile.edit')
                ->with('warning', 'Por segurança, altere a senha provisória antes de acessar os dados do estudante.');
        }

        return $next($request);
    }
}
