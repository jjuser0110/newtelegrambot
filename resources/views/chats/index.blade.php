@extends('layouts.app')
@section('title', 'Chats')

@php
    $tabIcons = ['starred' => 'bi-star-fill text-warning', 'recent' => 'bi-chat-dots', 'old' => 'bi-archive', 'all' => 'bi-list-ul'];
@endphp

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

<ul class="nav nav-tabs mb-3">
    @foreach(\App\Http\Controllers\ChatController::TABS as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
               href="{{ route('chats.index', [$bot, 'tab' => $key, 'days' => $days, 'q' => request('q')]) }}">
                <i class="bi {{ $tabIcons[$key] }}"></i> {{ $label }}
                <span class="badge rounded-pill text-bg-light border">{{ $tabCounts[$key] }}</span>
            </a>
        </li>
    @endforeach
</ul>

<form class="d-flex flex-wrap gap-2 mb-3 align-items-center" method="GET">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="input-group" style="max-width: 380px">
        <input name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, username or chat ID">
        <button class="btn btn-outline-secondary">Search</button>
    </div>
    <div class="d-flex align-items-center gap-2 small text-muted">
        <label for="days" class="text-nowrap">Old = no message for</label>
        <select name="days" id="days" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
            @foreach(\App\Http\Controllers\ChatController::OLD_AFTER_DAYS as $option)
                <option value="{{ $option }}" @selected($days === $option)>{{ $option }} day{{ $option > 1 ? 's' : '' }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <tr><th style="width: 44px"></th><th>Name</th><th>Username</th><th>Chat ID</th><th>Messages</th><th>Last message</th><th></th></tr>
            </thead>
            <tbody>
            @forelse($users as $u)
                <tr class="{{ $u->is_starred ? 'table-warning' : '' }} {{ $u->unread_count > 0 ? 'fw-semibold' : '' }}">
                    <td>
                        <form method="POST" action="{{ route('chats.star', [$bot, $u]) }}">
                            @csrf
                            <button class="btn btn-link p-0 fs-5 {{ $u->is_starred ? 'text-warning' : 'text-secondary' }}"
                                    title="{{ $u->is_starred ? 'Remove star' : 'Star this chat (important)' }}">
                                <i class="bi {{ $u->is_starred ? 'bi-star-fill' : 'bi-star' }}"></i>
                            </button>
                        </form>
                    </td>
                    <td>{{ trim($u->first_name.' '.$u->last_name) ?: '-' }}
                        @if($u->unread_count > 0)<span class="badge rounded-pill text-bg-danger" title="Unread messages">{{ $u->unread_count }} new</span>@endif
                        @if($u->is_blocked)<span class="badge text-bg-danger">blocked bot</span>@endif
                        @unless($u->auto_reply)<span class="badge text-bg-warning" title="Auto-reply is off; you chat manually">manual</span>@endunless</td>
                    <td>{{ $u->username ? '@'.$u->username : '-' }}</td>
                    <td><code>{{ $u->chat_id }}</code></td>
                    <td>{{ $u->messages_count }}</td>
                    <td>{{ $u->last_message_at?->diffForHumans() ?? '-' }}</td>
                    <td class="text-end"><a href="{{ route('chats.show', [$bot, $u]) }}" class="btn btn-sm btn-primary">Open chat</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">
                    @if($tab === 'starred')
                        No starred chats yet. Click the <i class="bi bi-star"></i> next to a chat to keep it here.
                    @elseif($tab === 'old')
                        No chats older than {{ $days }} day{{ $days > 1 ? 's' : '' }}.
                    @elseif($tab === 'recent')
                        No messages in the last {{ $days }} day{{ $days > 1 ? 's' : '' }}. Older chats are in the <b>Old</b> tab.
                    @else
                        Nobody has messaged this bot yet. Open <a href="https://t.me/{{ $bot->username }}" target="_blank" rel="noopener">t.me/{{ $bot->username }}</a> and press Start.
                    @endif
                </td></tr>
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
