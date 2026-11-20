@extends('layouts.app')
@section('title', $user->isSuperadmin() ? __('All Bots') : __('My Bots'))

@section('content')
@if($user->isSuperadmin())
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0">{{ __('All Bots') }} <span class="text-muted fs-6">({{ $bots->count() }})</span></h3>
    <form method="GET" class="d-flex gap-2">
        <select name="company" class="form-select" onchange="this.form.submit()">
            <option value="">{{ __('All companies') }}</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" @selected(request('company') == $company->id)>{{ $company->name }} ({{ $company->username }})</option>
            @endforeach
        </select>
    </form>
</div>
@else
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">{{ __('My Bots') }}</h3>
    <span class="badge text-bg-body-tertiary border fs-6">{{ __(':used / :limit bots used', ['used' => $bots->count(), 'limit' => $user->bot_limit]) }}</span>
</div>
@endif

@if($user->isSuperadmin())
    {{-- superadmin does not connect bots --}}
@elseif($user->canAddBot())
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title"><i class="bi bi-plug"></i> {{ __('Connect a Telegram bot') }}</h5>
            <p class="text-muted small mb-3">{!! __('Create a bot with :botfather, copy its token and paste it here.', ['botfather' => '<a href="https://t.me/BotFather" target="_blank" rel="noopener">@BotFather</a>']) !!}</p>
            <form method="POST" action="{{ route('bots.store') }}" class="row g-2"
                  data-loading="{{ __('Connecting to Telegram… checking the token and setting up the webhook. This can take up to 30 seconds.') }}">
                @csrf
                <div class="col-md-9">
                    <input name="token" value="{{ old('token') }}" class="form-control" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" required autocomplete="off">
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary"><i class="bi bi-link-45deg"></i> {{ __('Connect') }}</button>
                </div>
            </form>
        </div>
    </div>
@else
    <div class="alert alert-info">{{ __('You have reached your bot limit (:limit). Contact the administrator to add more.', ['limit' => $user->bot_limit]) }}</div>
@endif

<div class="row g-3">
    @forelse($bots as $bot)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    @if($user->isSuperadmin())
                        <div class="small text-muted mb-1"><i class="bi bi-building"></i> {{ $bot->user->name }} ({{ $bot->user->username }})</div>
                    @endif
                    <h5 class="card-title mb-1">{{ $bot->first_name }}</h5>
                    <a href="https://t.me/{{ $bot->username }}" target="_blank" rel="noopener" class="text-decoration-none">&#64;{{ $bot->username }}</a>
                    <div class="mt-2">
                        @if($bot->is_active)<span class="badge text-bg-success">{{ __('Active') }}</span>@else<span class="badge text-bg-secondary">{{ __('Paused') }}</span>@endif
                        @if($bot->webhook_set)<span class="badge text-bg-info">{{ __('Webhook') }}</span>@else<span class="badge text-bg-warning">{{ __('No webhook') }}</span>@endif
                    </div>
                    <div class="small text-muted mt-2">{{ __(':replies replies · :users users', ['replies' => $bot->replies_count, 'users' => $bot->telegram_users_count]) }}</div>
                </div>
                <div class="card-footer bg-body border-0 d-flex gap-2">
                    <a href="{{ route('bots.show', $bot) }}" class="btn btn-sm btn-primary">{{ __('Replies') }}</a>
                    <a href="{{ route('chats.index', $bot) }}" class="btn btn-sm btn-outline-primary">{{ __('Chats') }}</a>
                    <a href="{{ route('broadcasts.index', $bot) }}" class="btn btn-sm btn-outline-primary">{{ __('Broadcasts') }}</a>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-muted">{{ __('No bots connected yet.') }}</div>
    @endforelse
</div>
@endsection
