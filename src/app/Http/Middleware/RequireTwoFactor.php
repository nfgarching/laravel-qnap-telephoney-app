<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Angemeldete Benutzer ohne bestätigte Zwei-Faktor-Anmeldung dürfen nur die
 * Kontoseite (2FA einrichten) und die dafür nötigen Fortify-Routen aufrufen.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! config('auth.require_two_factor') || $user->two_factor_confirmed_at) {
            return $next($request);
        }

        if ($request->routeIs('account', 'logout', 'two-factor.*', 'password.confirm*', 'user-password.update')) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
            abort(403, 'Zwei-Faktor-Anmeldung einrichten.');
        }

        return redirect()->route('account');
    }
}
