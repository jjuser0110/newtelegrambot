<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['username' => __('Invalid username or password.')]);
        }

        if (! Auth::user()->is_active) {
            Auth::logout();
            throw ValidationException::withMessages(['username' => __('Your account is disabled.')]);
        }

        $request->session()->regenerate();

        // Keep the language picked on the login page for this account.
        if ($request->session()->has('locale')) {
            Auth::user()->update(['locale' => $request->session()->get('locale')]);
        }

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function home(Request $request)
    {
        return $request->user()->isSuperadmin()
            ? redirect()->route('admin.companies.index')
            : redirect()->route('bots.index');
    }
}
