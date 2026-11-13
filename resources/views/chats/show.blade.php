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
                            @endif
                            @if($m->message !== null && $m->message !== ''){{ $m->message }}@endif
                            @foreach($m->payload['buttons'] ?? [] as $b)
                                <div class="small border rounded px-2 mt-1">{{ $b['text'] }}</div>
                            @endforeach
                            <div class="small opacity-75 text-end mt-1">{{ $m->created_at->format('Y-m-d H:i') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted text-center">No messages.</div>
                @endforelse
            </div>
            <div class="card-footer bg-white">
                <form method="POST" action="{{ route('chats.send', [$bot, $tgUser]) }}" enctype="multipart/form-data">
                    @csrf
                    <textarea name="message" rows="3" class="form-control mb-2" placeholder="Type a message to send via &#64;{{ $bot->username }}" maxlength="4096">{{ old('message') }}</textarea>
                    <div class="row g-2 mb-2">
                        <div class="col-md-8"><input type="file" name="image" accept="image/*" class="form-control"></div>
                        <div class="col-md-4 d-grid"><button class="btn btn-primary"><i class="bi bi-send"></i> Send</button></div>
                    </div>
                    <details>
                        <summary class="small text-muted">Add buttons</summary>
                        <div class="mt-2">@include('partials.buttons', ['buttons' => []])</div>
                    </details>
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
<script>const box = document.getElementById('chat-box'); box.scrollTop = box.scrollHeight;</script>
@endpush
