@extends('layouts.app')
@section('title', 'Chats')

@section('content')
<nav class="mb-2"><a href="{{ route('bots.show', $bot) }}">&larr; &#64;{{ $bot->username }}</a></nav>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0">Telegram users of &#64;{{ $bot->username }}
        <span class="badge text-bg-light border fs-6 fw-normal align-middle" title="This list refreshes when new messages arrive">
            <span class="spinner-grow spinner-grow-sm text-success" style="width: .5rem; height: .5rem;"></span> live
        </span>
    </h3>
    <a href="{{ route('broadcasts.index', $bot) }}" class="btn btn-outline-primary"><i class="bi bi-megaphone"></i> Broadcasts</a>
</div>

<form class="mb-3" method="GET">
    <div class="input-group" style="max-width: 380px">
        <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, username or chat ID">
        <button class="btn btn-outline-secondary">Search</button>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th>Name</th><th>Username</th><th>Chat ID</th><th>Messages</th><th>Last message</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td>{{ trim($u->first_name.' '.$u->last_name) ?: '-' }}
                        @if($u->is_blocked)<span class="badge text-bg-danger">blocked bot</span>@endif
                        @unless($u->auto_reply)<span class="badge text-bg-warning" title="Auto-reply is off; you chat manually">manual</span>@endunless</td>
                    <td>{{ $u->username ? '@'.$u->username : '-' }}</td>
                    <td><code>{{ $u->chat_id }}</code></td>
                    <td>{{ $u->messages_count }}</td>
                    <td>{{ $u->last_message_at?->diffForHumans() ?? '-' }}</td>
                    <td class="text-end"><a href="{{ route('chats.show', [$bot, $u]) }}" class="btn btn-sm btn-primary">Open chat</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Nobody has messaged this bot yet. Open <a href="https://t.me/{{ $bot->username }}" target="_blank" rel="noopener">t.me/{{ $bot->username }}</a> and press Start.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
@endsection

@push('scripts')
<script>
(() => {
    const url = @json(route('chats.latest', $bot));
    let latest = {{ $latestMessageId }};

    /** Reload the list when a new message arrives (but not while typing a search). */
    setInterval(async () => {
        if (document.hidden || document.activeElement?.name === 'q') {
            return;
        }
        try {
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            if (data.latest_message_id !== latest) {
                window.location.reload();
            }
        } catch (e) {
            // try again next tick
        }
    }, 5000);
})();
</script>
@endpush
