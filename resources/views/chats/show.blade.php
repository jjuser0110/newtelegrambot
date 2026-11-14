@extends('layouts.app')
@section('title', $tgUser->displayName())

@section('content')
<nav class="mb-2"><a href="{{ route('chats.index', $bot) }}">&larr; All chats</a></nav>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <b>{{ $tgUser->displayName() }}</b>
                @if($tgUser->username)<span class="text-muted">&#64;{{ $tgUser->username }}</span>@endif
                @if($tgUser->is_blocked)<span class="badge text-bg-danger">blocked bot</span>@endif
            </div>
            <div class="card-body chat-box" id="chat-box">
                @forelse($messages as $m)
                    <div class="d-flex mb-2 {{ $m->direction === 'out' ? 'justify-content-end' : '' }}">
                        <div class="bubble p-2 rounded shadow-sm {{ $m->direction === 'out' ? 'bg-primary text-white' : 'bg-white' }}">
                            @if($m->image_path)
                                <img src="{{ asset('storage/'.$m->image_path) }}" class="d-block mb-1" alt="">
                            @elseif($m->type === 'photo' && $m->direction === 'in')
                                <div class="fst-italic small"><i class="bi bi-image"></i> [photo]</div>
                            @elseif($m->type === 'callback')
                                <div class="fst-italic small"><i class="bi bi-hand-index"></i> pressed button:</div>
                            @elseif($m->type === 'other')
                                <div class="fst-italic small">[non-text message]</div>
                            @elseif(in_array($m->type, ['copy', 'forward'], true))
                                <div class="fst-italic small"><i class="bi bi-bookmark-star"></i> {{ $m->type === 'forward' ? 'Forwarded' : 'Copied' }} saved message: {{ $m->payload['saved_label'] ?? '' }}</div>
                            @endif
                            @if($m->message !== null && $m->message !== '')<div class="msg-text">{{ $m->message }}</div>@endif
                            @foreach($m->payload['buttons'] ?? [] as $b)
                                <div class="small border rounded px-2 mt-1">{{ $b['text'] }}</div>
                            @endforeach
                            <div class="small opacity-75 d-flex justify-content-between gap-3 mt-1">
                                <span>
                                    @if($m->telegram_message_id)msg #{{ $m->telegram_message_id }}@endif
                                    @if($m->direction === 'in' && $m->telegram_message_id && $m->type !== 'callback')
                                        @if(isset($savedKeys[$m->chat_id.':'.$m->telegram_message_id]))
                                            · <span class="text-success"><i class="bi bi-bookmark-check"></i> saved</span>
                                        @else
                                            · <form method="POST" action="{{ route('bots.saved-messages.store', $bot) }}" class="d-inline save-message-form">
                                                @csrf
                                                <input type="hidden" name="telegram_message_id" value="{{ $m->id }}">
                                                <input type="hidden" name="label" value="">
                                                <button class="btn btn-link btn-sm p-0 align-baseline" title="Save to reuse as a reply or broadcast"><i class="bi bi-bookmark-plus"></i> Save</button>
                                            </form>
                                        @endif
                                    @endif
                                </span>
                                <span>{{ $m->created_at->format('Y-m-d H:i') }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted text-center">No messages.</div>
                @endforelse
            </div>
            <div class="card-footer bg-white">
                <form method="POST" action="{{ route('chats.send', [$bot, $tgUser]) }}" enctype="multipart/form-data" data-loading="Sending message…">
                    @csrf
                    <div class="compose-only">
                        <textarea name="message" rows="3" class="form-control mb-2" placeholder="Type a message to send via &#64;{{ $bot->username }}" maxlength="4096">{{ old('message') }}</textarea>
                        <input type="file" name="image" accept="image/*" class="form-control mb-2">
                    </div>
                    <details class="mb-2" @if($savedMessages->isNotEmpty() && old('saved_message_id')) open @endif>
                        <summary class="small text-muted">Reply with a saved message (copy / forward)</summary>
                        <div class="mt-2">@include('partials.saved-message-picker', ['savedMessages' => $savedMessages, 'bot' => $bot])</div>
                    </details>
                    <details class="mb-2">
                        <summary class="small text-muted">Add buttons</summary>
                        <div class="mt-2">@include('partials.buttons', ['buttons' => []])</div>
                    </details>
                    <button class="btn btn-primary"><i class="bi bi-send"></i> Send</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body small">
                <h6>User details</h6>
                <dl class="row mb-0">
                    <dt class="col-5">Chat ID</dt><dd class="col-7"><code>{{ $tgUser->chat_id }}</code></dd>
                    <dt class="col-5">First name</dt><dd class="col-7">{{ $tgUser->first_name ?? '-' }}</dd>
                    <dt class="col-5">Last name</dt><dd class="col-7">{{ $tgUser->last_name ?? '-' }}</dd>
                    <dt class="col-5">Username</dt><dd class="col-7">{{ $tgUser->username ? '@'.$tgUser->username : '-' }}</dd>
                    <dt class="col-5">Language</dt><dd class="col-7">{{ $tgUser->language_code ?? '-' }}</dd>
                    <dt class="col-5">First seen</dt><dd class="col-7">{{ $tgUser->created_at->format('Y-m-d H:i') }}</dd>
                    <dt class="col-5">Last message</dt><dd class="col-7">{{ $tgUser->last_message_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const box = document.getElementById('chat-box');
box.scrollTop = box.scrollHeight;
document.querySelectorAll('.save-message-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const label = prompt('Name for this saved message (e.g. "Promo Nov"):', '');
        if (label === null) {
            event.preventDefault();
            return;
        }
        form.querySelector('[name=label]').value = label;
    });
});
</script>
@endpush
