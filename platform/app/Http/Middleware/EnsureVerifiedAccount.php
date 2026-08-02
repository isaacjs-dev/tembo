<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVerifiedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $settings = is_array($user?->settings) ? $user->settings : [];
        $verificationRequired = (bool) ($settings['requires_email_verification'] ?? false);

        if (
            $verificationRequired
            && $user instanceof MustVerifyEmail
            && ! $user->hasVerifiedEmail()
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Confirme seu endereço de e-mail antes de continuar.',
                ], 403);
            }

            return redirect()->guest(route('verification.notice'));
        }

        return $next($request);
    }
}
