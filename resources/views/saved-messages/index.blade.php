@extends('layouts.app')
@section('title', __('Saved messages'))

@section('content')
<nav class="mb-2"><a href="{{ route('bots.show', $bot) }}">&larr; &#64;{{ $bot->username }}</a></nav>
<h3 class="mb-1">{{ __('Saved messages') }}</h3>
<p class="text-muted">
    {!! __('Messages that already exist in Telegram (for example a customer\'s or your own message decorated with Telegram Premium emoji), which the bot can <b>copy</b> or <b>forward</b> as an auto-reply, a chat reply or a broadcast.') !!}
    {!! __('Easiest way: send the decorated message to :bot from your own Telegram, then open your chat here and click <b>Save</b> on it.', ['bot' => '<a href="https://t.me/'.e($bot->username).'" target="_blank" rel="noopener">@'.e($bot->username).'</a>']) !!}
    {{ __('The original message must not be deleted, otherwise Telegram can no longer copy it.') }}
</p>

<div class="card border-0 shadow-sm mb-4">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light"><tr><th>{{ __('Label') }}</th><th>{{ __('Preview') }}</th><th>{{ __('From chat_id') }}</th><th>{{ __('Message id') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($savedMessages as $saved)
                <tr>
                    <td style="min-width: 220px">
                        <form method="POST" action="{{ route('bots.saved-messages.update', [$bot, $saved]) }}" class="input-group input-group-sm">
                            @csrf @method('PUT')
                            <input name="label" value="{{ $saved->label }}" class="form-control" required maxlength="100">
                            <button class="btn btn-outline-secondary" title="{{ __('Rename') }}"><i class="bi bi-check-lg"></i></button>
                        </form>
                    </td>
                    <td class="small" style="white-space: pre-wrap">{{ \Illuminate\Support\Str::limit($saved->preview ?? '—', 160) }}
                        @if($saved->has_custom_emoji)<span class="badge text-bg-warning">✨ {{ __('Premium emoji') }}</span>@endif</td>
                    <td><code>{{ $saved->from_chat_id }}</code></td>
                    <td><code>{{ $saved->message_id }}</code></td>
                    <td class="text-end">
                        <form method="POST" action="{{ route('bots.saved-messages.destroy', [$bot, $saved]) }}" onsubmit="return confirm(@js(__('Remove this saved message? Replies and broadcasts using it will stop using it.')))">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">{{ __('Remove') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">{{ __('No saved messages yet.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm" style="max-width: 640px">
    <div class="card-body">
        <h6>{{ __('Add by chat_id and message_id') }}</h6>
        <form method="POST" action="{{ route('bots.saved-messages.store', $bot) }}" class="row g-2">
            @csrf
            <div class="col-md-4"><input name="label" class="form-control" placeholder="{{ __('Label') }}" value="{{ old('label') }}"></div>
            <div class="col-md-3"><input name="from_chat_id" class="form-control" placeholder="chat_id" value="{{ old('from_chat_id') }}" required></div>
            <div class="col-md-3"><input name="message_id" class="form-control" placeholder="message_id" value="{{ old('message_id') }}" required></div>
            <div class="col-md-2 d-grid"><button class="btn btn-primary">{{ __('Add') }}</button></div>
        </form>
    </div>
</div>
@endsection
