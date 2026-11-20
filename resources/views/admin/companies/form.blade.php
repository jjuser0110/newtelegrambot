@extends('layouts.app')
@section('title', $company->exists ? __('Edit Company') : __('New Company'))

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <h3 class="mb-3">{{ $company->exists ? __('Edit Company') : __('New Company') }}</h3>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ $company->exists ? route('admin.companies.update', $company) : route('admin.companies.store') }}">
                    @csrf
                    @if($company->exists) @method('PUT') @endif

                    <div class="mb-3">
                        <label class="form-label">{{ __('Company name') }}</label>
                        <input name="name" value="{{ old('name', $company->name) }}" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Username (used to log in)') }}</label>
                        <input name="username" value="{{ old('username', $company->username) }}" class="form-control" required>
                        <div class="form-text">{{ __('Letters, numbers, dashes and underscores only.') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Password') }}</label>
                        <input type="text" name="password" class="form-control" {{ $company->exists ? '' : 'required' }} autocomplete="new-password">
                        @if($company->exists)<div class="form-text">{{ __('Leave blank to keep the current password.') }}</div>@endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Bot limit') }}</label>
                        <input type="number" name="bot_limit" min="0" value="{{ old('bot_limit', $company->bot_limit) }}" class="form-control" required>
                        <div class="form-text">{{ __('How many Telegram bots this company can connect.') }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Panel language') }}</label>
                        <select name="locale" class="form-select">
                            @foreach(\App\Http\Middleware\SetLocale::LOCALES as $code => $name)
                                <option value="{{ $code }}" @selected(old('locale', $company->locale ?? 'en') === $code)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">{{ __('The company can change this later from the top bar.') }}</div>
                    </div>
                    <div class="form-check form-switch mb-4">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active"
                            {{ old('is_active', $company->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_active">{{ __('Active (can log in, bots auto-reply)') }}</label>
                    </div>
                    <button class="btn btn-primary">{{ __('Save') }}</button>
                    <a href="{{ route('admin.companies.index') }}" class="btn btn-link">{{ __('Cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
