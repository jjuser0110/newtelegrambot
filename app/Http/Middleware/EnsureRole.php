<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['username' => 'Your account is disabled.']);
        }

        abort_unless($user->role === $role, 403);

        return $next($request);
    }
}
