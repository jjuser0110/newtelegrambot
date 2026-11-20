<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch the panel language: saved on the account when logged in, otherwise in the session (login page).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(SetLocale::LOCALES))],
        ]);

        $request->session()->put('locale', $data['locale']);
        $request->user()?->update(['locale' => $data['locale']]);

        return back();
    }
}
