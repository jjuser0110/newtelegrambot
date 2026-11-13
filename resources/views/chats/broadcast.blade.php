@extends('layouts.app')
@section('title', 'Broadcast')

@section('content')
<nav class="mb-2"><a href="{{ route('chats.index', $bot) }}">&larr; All chats</a></nav>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <h3 class="mb-1">Broadcast via &#64;{{ $bot->username }}</h3>
        <p class="text-muted">Sends to <b>{{ $count }}</b> Telegram users who have messaged this bot (blocked users are skipped).</p>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('chats.broadcast.send', $bot) }}" enctype="multipart/form-data"
                      onsubmit="return confirm('Send this message to {{ $count }} users?')">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Message</label>
                        <textarea name="message" rows="6" class="form-control" maxlength="4096">{{ old('message') }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Image (optional)</label>
                        <input type="file" name="image" accept="image/*" class="form-control">
                    </div>
                    <div class="mb-4">@include('partials.buttons', ['buttons' => []])</div>
                    <button class="btn btn-primary" @disabled($count === 0)><i class="bi bi-megaphone"></i> Send broadcast</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
