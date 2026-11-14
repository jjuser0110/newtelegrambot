@extends('layouts.app')
@section('title', 'Broadcast #'.$broadcast->id)

@php
    $statusColors = ['scheduled' => 'info', 'running' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary'];
@endphp

@section('content')
<nav class="mb-2"><a href="{{ route('broadcasts.index', $bot) }}">&larr; Broadcasts</a></nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0">Broadcast #{{ $broadcast->id }} {{ $broadcast->title ? '– '.$broadcast->title : '' }}</h3>
    <div class="d-flex gap-2">
        @if($broadcast->failed_count > 0 && in_array($broadcast->status, ['running', 'completed'], true))
            <form method="POST" action="{{ route('broadcasts.retry', [$bot, $broadcast]) }}" onsubmit="return confirm('Send again to all failed recipients?')">@csrf
                <button class="btn btn-outline-warning"><i class="bi bi-arrow-clockwise"></i> Retry failed</button></form>
        @endif
        @unless($broadcast->isFinished())
            <form method="POST" action="{{ route('broadcasts.cancel', [$bot, $broadcast]) }}" onsubmit="return confirm('Stop this broadcast? Remaining users will not receive it.')">@csrf
                <button class="btn btn-outline-danger"><i class="bi bi-stop-circle"></i> Cancel</button></form>
        @else
            <form method="POST" action="{{ route('broadcasts.destroy', [$bot, $broadcast]) }}" onsubmit="return confirm('Delete this broadcast and its report?')">@csrf @method('DELETE')
                <button class="btn btn-outline-secondary">Delete</button></form>
        @endunless
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Status: <span id="status" class="badge text-bg-{{ $statusColors[$broadcast->status] ?? 'light' }}">{{ $broadcast->status }}</span></span>
                    <span id="percent" class="fw-semibold">{{ $broadcast->progressPercent() }}%</span>
                </div>
                <div class="progress mb-3" style="height: 22px">
                    <div id="bar-sent" class="progress-bar bg-success" style="width: {{ $broadcast->total ? $broadcast->sent_count * 100 / $broadcast->total : 0 }}%"></div>
                    <div id="bar-failed" class="progress-bar bg-danger" style="width: {{ $broadcast->total ? $broadcast->failed_count * 100 / $broadcast->total : 0 }}%"></div>
                </div>
                <div class="row text-center">
                    <div class="col"><div class="fs-4" id="total">{{ $broadcast->total }}</div><div class="small text-muted">Total</div></div>
                    <div class="col"><div class="fs-4 text-success" id="sent">{{ $broadcast->sent_count }}</div><div class="small text-muted">Sent</div></div>
                    <div class="col"><div class="fs-4 text-danger" id="failed">{{ $broadcast->failed_count }}</div><div class="small text-muted">Failed</div></div>
                    <div class="col"><div class="fs-4 text-secondary" id="pending">{{ max(0, $broadcast->total - $broadcast->sent_count - $broadcast->failed_count) }}</div><div class="small text-muted">Waiting</div></div>
                </div>
                <div class="small text-muted mt-3" id="note">
                    @if($broadcast->status === 'scheduled')
                        Will start at {{ $broadcast->scheduled_at?->format('Y-m-d H:i') }} ({{ config('app.timezone') }}).
                    @elseif($broadcast->status === 'running' && $broadcast->next_batch_at?->isFuture())
                        Pausing between batches — next batch at {{ $broadcast->next_batch_at->format('Y-m-d H:i') }}.
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">Content</dt><dd class="col-7">{{ $broadcast->contentSummary() }}</dd>
                    <dt class="col-5">Scheduled</dt><dd class="col-7">{{ $broadcast->scheduled_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                    <dt class="col-5">Started</dt><dd class="col-7">{{ $broadcast->started_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                    <dt class="col-5">Finished</dt><dd class="col-7">{{ $broadcast->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                    <dt class="col-5">Batches</dt><dd class="col-7">{{ $broadcast->batch_size }} per batch, {{ $broadcast->batch_delay_minutes }} min pause</dd>
                    <dt class="col-5">Created by</dt><dd class="col-7">{{ $broadcast->creator?->username ?? '-' }}</dd>
                </dl>
                @if($broadcast->message)
                    <hr><div style="white-space: pre-wrap">{{ \Illuminate\Support\Str::limit($broadcast->message, 400) }}</div>
                @endif
                @if($broadcast->image_path)
                    <img src="{{ asset('storage/'.$broadcast->image_path) }}" class="img-fluid rounded mt-2" alt="">
                @endif
            </div>
        </div>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h5 class="mb-0">Recipients</h5>
    <div class="btn-group btn-group-sm">
        @foreach(['' => 'All', 'sent' => 'Sent', 'failed' => 'Failed', 'pending' => 'Waiting'] as $value => $label)
            <a href="{{ route('broadcasts.show', [$bot, $broadcast, 'status' => $value ?: null]) }}"
               class="btn {{ request('status', '') === $value ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>Chat ID</th><th>Name</th><th>Status</th><th>Sent time</th><th>Message id</th><th>Error</th></tr></thead>
            <tbody>
            @forelse($recipients as $recipient)
                <tr>
                    <td><code>{{ $recipient->chat_id }}</code></td>
                    <td>
                        @if($recipient->telegramUser)
                            <a href="{{ route('chats.show', [$bot, $recipient->telegramUser]) }}">{{ $recipient->telegramUser->displayName() }}</a>
                        @else - @endif
                    </td>
                    <td>
                        @if($recipient->status === 'sent')<span class="badge text-bg-success">sent</span>
                        @elseif($recipient->status === 'failed')<span class="badge text-bg-danger">failed</span>
                        @else<span class="badge text-bg-secondary">{{ $broadcast->status === 'cancelled' ? 'not sent' : 'waiting' }}</span>@endif
                    </td>
                    <td class="small">{{ $recipient->sent_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                    <td class="small">{{ $recipient->telegram_message_id ?? '-' }}</td>
                    <td class="small text-danger">{{ $recipient->error }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">
                    {{ $broadcast->status === 'scheduled' ? 'The recipient list is created when the broadcast starts.' : 'No recipients.' }}
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $recipients->links('pagination::bootstrap-5') }}</div>
@endsection

@push('scripts')
@unless($broadcast->isFinished())
<script>
(() => {
    const url = @json(route('broadcasts.progress', [$bot, $broadcast]));
    const colors = @json($statusColors);
    const startedAs = @json($broadcast->status);
    const set = (id, value) => { document.getElementById(id).textContent = value; };

    const poll = async () => {
        try {
            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            set('total', data.total);
            set('sent', data.sent);
            set('failed', data.failed);
            set('pending', data.pending);
            set('percent', data.percent + '%');
            const status = document.getElementById('status');
            status.textContent = data.status;
            status.className = 'badge text-bg-' + (colors[data.status] || 'light');
            document.getElementById('bar-sent').style.width = (data.total ? data.sent * 100 / data.total : 0) + '%';
            document.getElementById('bar-failed').style.width = (data.total ? data.failed * 100 / data.total : 0) + '%';
            set('note', data.next_batch_at && data.pending > 0
                ? 'Pausing between batches — next batch at ' + data.next_batch_at + '.'
                : (data.status === 'running' ? 'Sending…' : ''));

            if (data.finished || data.status !== startedAs) {
                // Reload once so the recipient table and buttons are up to date.
                setTimeout(() => window.location.reload(), 800);
                return;
            }
        } catch (e) {
            // ignore and try again
        }
        setTimeout(poll, 3000);
    };
    setTimeout(poll, 2000);
})();
</script>
@endunless
@endpush
