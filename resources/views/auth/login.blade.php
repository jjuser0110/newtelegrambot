@extends('layouts.app')
@section('title', __('Login'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0 mt-5">
            <div class="card-body p-4">
                <h4 class="mb-4 text-center"><i class="bi bi-telegram text-primary"></i> {{ __('Sign in') }}</h4>
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('Username') }}</label>
                        <input name="username" value="{{ old('username') }}" class="form-control" required autofocus autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Password') }}</label>
                        <input type="password" name="password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
                    </div>
                    <button class="btn btn-primary w-100">{{ __('Login') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
