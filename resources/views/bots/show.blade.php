@extends('layouts.app')
@section('title', '@'.$bot->username)

@section('content')
<nav class="mb-2"><a href="{{ route('bots.index') }}">&larr; {{ auth()->user()->isSuperadmin() ? __('All Bots') : __('My Bots') }}</a></nav>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="mb-0">{{ $bot->first_name }}
                <a href="https://t.me/{{ $bot->username }}" target="_blank" rel="noopener" class="fs-6">&#64;{{ $bot->username }}</a></h3>
            <div class="small text-muted mt-1">
                {{ __('Bot ID :id · :users users · :messages messages', ['id' => $bot->telegram_bot_id, 'users' => $bot->telegram_users_count, 'messages' => $bot->messages_count]) }} ·
                @if($bot->webhook_set)<span class="text-success">{{ __('webhook active') }}</span>@else<span class="text-warning">{{ __('webhook not set') }}</span>@endif
                · @if($bot->is_active)<span class="text-success">{{ __('auto-reply on') }}</span>@else<span class="text-secondary">{{ __('paused') }}</span>@endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('chats.index', $bot) }}" class="btn btn-primary"><i class="bi bi-chat-dots"></i> {{ __('Chats') }}</a>
            <a href="{{ route('broadcasts.index', $bot) }}" class="btn btn-outline-primary"><i class="bi bi-megaphone"></i> {{ __('Broadcasts') }}</a>
            <a href="{{ route('bots.saved-messages.index', $bot) }}" class="btn btn-outline-primary"><i class="bi bi-bookmark-star"></i> {{ __('Saved messages') }}</a>
            <form method="POST" action="{{ route('bots.webhook', $bot) }}" data-loading="{{ __('Registering the webhook with Telegram…') }}">@csrf
                <button class="btn btn-outline-secondary" title="{{ $bot->webhookUrl() }}"><i class="bi bi-arrow-repeat"></i> {{ __('Set webhook') }}</button></form>
            <form method="POST" action="{{ route('bots.toggle', $bot) }}">@csrf
                <button class="btn btn-outline-secondary">{{ $bot->is_active ? __('Pause') : __('Enable') }}</button></form>
            <form method="POST" action="{{ route('bots.destroy', $bot) }}" onsubmit="return confirm(@js(__('Disconnect this bot? All replies, chats and messages will be deleted.')))">
                @csrf @method('DELETE')<button class="btn btn-outline-danger">{{ __('Disconnect') }}</button></form>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
    <h4 class="mb-0">{{ __('Auto replies') }}</h4>
    <a href="{{ route('bots.replies.create', $bot) }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add reply') }}</a>
</div>
<p class="text-muted small">
    {!! __('When a user sends a message, every active reply whose <b>trigger</b> matches is sent in order. Use <code>/start</code> for when someone starts the bot, any word (e.g. <code>price</code>) for keywords, or <code>*</code> as the fallback when nothing else matches. A <i>keyword button</i> sends the reply with that trigger.') !!}
</p>

<div class="row g-3">
    @forelse($replies as $reply)
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100 {{ $reply->is_active ? '' : 'opacity-50' }}">
                @if($reply->image_path)
                    <img src="{{ asset('storage/'.$reply->image_path) }}" class="card-img-top" style="max-height:180px;object-fit:cover" alt="">
                @endif
                <div class="card-body">
                    <div class="mb-2"><span class="badge text-bg-dark">{{ $reply->trigger }}</span>
                        <span class="badge text-bg-body-tertiary border">#{{ $reply->sort_order }}</span>
                        @unless($reply->is_active)<span class="badge text-bg-secondary">{{ __('inactive') }}</span>@endunless</div>
                    @if($reply->savedMessage)
                        <div class="small text-primary mb-1"><i class="bi bi-bookmark-star"></i>
                            {{ $reply->send_mode === 'forward' ? __('Forward saved message: :label', ['label' => $reply->savedMessage->label]) : __('Copy saved message: :label', ['label' => $reply->savedMessage->label]) }}</div>
                        <div style="white-space:pre-wrap" class="small">{{ \Illuminate\Support\Str::limit($reply->savedMessage->preview, 300) }}</div>
                    @else
                        <div style="white-space:pre-wrap" class="small">{{ \Illuminate\Support\Str::limit($reply->message, 300) }}</div>
                    @endif
                    @include('partials.button-preview', ['buttons' => $reply->buttons ?? []])
                </div>
                <div class="card-footer bg-body border-0 d-flex gap-2">
                    <a href="{{ route('bots.replies.edit', [$bot, $reply]) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                    <form method="POST" action="{{ route('bots.replies.destroy', [$bot, $reply]) }}" onsubmit="return confirm(@js(__('Delete this reply?')))">
                        @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button></form>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-muted">{{ __('No replies yet.') }}</div>
    @endforelse
</div>
@endsection
