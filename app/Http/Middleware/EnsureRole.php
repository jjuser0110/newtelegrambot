<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Usage: role:superadmin  or  role:company,superadmin
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['username' => __('Your account is disabled.')]);
        }

        abort_unless(in_array($user->role, $roles, true), 403);

        return $next($request);
    }
}
