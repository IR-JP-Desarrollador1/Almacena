<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->status !== User::STATUS_ACTIVE) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Tu cuenta no está activa. Contacta al administrador.'),
            ]);
        }

        $sessionVersion = $request->session()->get('auth_session_version');

        if ($sessionVersion !== null && $sessionVersion !== $user->session_version) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('Tu sesión fue revocada. Inicia sesión nuevamente.'),
            ]);
        }

        $request->session()->put('auth_session_version', $user->session_version);

        return $next($request);
    }
}
