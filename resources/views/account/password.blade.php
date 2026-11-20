@extends('layouts.app')
@section('title', __('Change password'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <h3 class="mb-3"><i class="bi bi-key"></i> {{ __('Change password') }}</h3>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="current_password">{{ __('Current password') }}</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">{{ __('New password') }}</label>
                        <input type="password" name="password" id="password" class="form-control" required minlength="6" autocomplete="new-password">
                        <div class="form-text">{{ __('At least 6 characters.') }}</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">{{ __('Confirm new password') }}</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required minlength="6" autocomplete="new-password">
                    </div>
                    <button class="btn btn-primary">{{ __('Update password') }}</button>
                    <a href="{{ route('home') }}" class="btn btn-link">{{ __('Cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
