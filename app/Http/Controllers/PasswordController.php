<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets any logged-in user (superadmin or company) change their own password.
 */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed', 'different:current_password'],
        ]);

        $request->user()->update(['password' => $data['password']]);
        $request->session()->regenerate();

        return redirect()->route('password.edit')->with('success', __('Your password has been changed.'));
    }
}
