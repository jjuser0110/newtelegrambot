<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = User::where('role', User::ROLE_COMPANY)
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('username', 'like', "%$s%")))
            ->withCount('bots')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('admin.companies.form', ['company' => new User(['bot_limit' => 1, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6'],
            'bot_limit' => ['required', 'integer', 'min:0', 'max:1000'],
            'locale' => ['nullable', Rule::in(array_keys(SetLocale::LOCALES))],
            'is_active' => ['boolean'],
        ]);

        User::create($data + ['role' => User::ROLE_COMPANY, 'is_active' => $request->boolean('is_active')]);

        return redirect()->route('admin.companies.index')->with('success', __('Company created.'));
    }

    public function edit(User $company)
    {
        $this->ensureCompany($company);

        return view('admin.companies.form', compact('company'));
    }

    public function update(Request $request, User $company)
    {
        $this->ensureCompany($company);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($company->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'bot_limit' => ['required', 'integer', 'min:0', 'max:1000'],
            'locale' => ['nullable', Rule::in(array_keys(SetLocale::LOCALES))],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }
        $data['is_active'] = $request->boolean('is_active');

        $company->update($data);

        return redirect()->route('admin.companies.index')->with('success', __('Company updated.'));
    }

    public function destroy(User $company)
    {
        $this->ensureCompany($company);

        foreach ($company->bots as $bot) {
            try {
                $bot->api()->deleteWebhook();
            } catch (\Throwable) {
                // ignore – token may already be revoked
            }
        }
        $company->delete();

        return redirect()->route('admin.companies.index')->with('success', __('Company deleted.'));
    }

    private function ensureCompany(User $user): void
    {
        abort_unless($user->role === User::ROLE_COMPANY, 404);
    }
}
