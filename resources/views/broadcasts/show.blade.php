@extends('layouts.app')
@section('title', __('Broadcast #:id', ['id' => $broadcast->id]))

@php
    $statusColors = ['scheduled' => 'info', 'running' => 'primary', 'paused' => 'warning', 'completed' => 'success', 'cancelled' => 'secondary'];
    $statusLabels = collect(array_keys($statusColors))->mapWithKeys(fn ($status) => [$status => __(ucfirst($status))]);
@endphp

@section('content')
<nav class="mb-2"><a href="{{ route('broadcasts.index', $bot) }}">&larr; {{ __('Broadcasts') }}</a></nav>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h3 class="mb-0">{{ __('Broadcast #:id', ['id' => $broadcast->id]) }} {{ $broadcast->title ? '– '.$broadcast->title : '' }}</h3>
    <div class="d-flex gap-2">
        @if($broadcast->failed_count > 0 && in_array($broadcast->status, ['running', 'completed'], true))
            <form method="POST" action="{{ route('broadcasts.retry', [$bot, $broadcast]) }}" onsubmit="return confirm(@js(__('Send again to all failed recipients?')))">@csrf
                <button class="btn btn-outline-warning"><i class="bi bi-arrow-clockwise"></i> {{ __('Retry failed') }}</button></form>
        @endif
        @if($broadcast->status === 'running')
            <form method="POST" action="{{ route('broadcasts.pause', [$bot, $broadcast]) }}">@csrf
                <button class="btn btn-outline-warning"><i class="bi bi-pause-circle"></i> {{ __('Pause') }}</button></form>
        @elseif($broadcast->status === 'paused')
            <form method="POST" action="{{ route('broadcasts.resume', [$bot, $broadcast]) }}">@csrf
                <button class="btn btn-success"><i class="bi bi-play-circle"></i> {{ __('Resume') }}</button></form>
        @endif
        @unless($broadcast->isFinished())
            <form method="POST" action="{{ route('broadcasts.cancel', [$bot, $broadcast]) }}" onsubmit="return confirm(@js(__('Stop this broadcast? Remaining users will not receive it.')))">@csrf
                <button class="btn btn-outline-danger"><i class="bi bi-stop-circle"></i> {{ __('Cancel') }}</button></form>
        @else
            <form method="POST" action="{{ route('broadcasts.destroy', [$bot, $broadcast]) }}" onsubmit="return confirm(@js(__('Delete this broadcast and its report?')))">@csrf @method('DELETE')
                <button class="btn btn-outline-secondary">{{ __('Delete') }}</button></form>
        @endunless
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>{{ __('Status') }}: <span id="status" class="badge text-bg-{{ $statusColors[$broadcast->status] ?? 'light' }}">{{ $statusLabels[$broadcast->status] ?? $broadcast->status }}</span></span>
                    <span id="percent" class="fw-semibold">{{ $broadcast->progressPercent() }}%</span>
                </div>
                <div class="progress mb-3" style="height: 22px">
                    <div id="bar-sent" class="progress-bar bg-success" style="width: {{ $broadcast->total ? $broadcast->sent_count * 100 / $broadcast->total : 0 }}%"></div>
                    <div id="bar-failed" class="progress-bar bg-danger" style="width: {{ $broadcast->total ? $broadcast->failed_count * 100 / $broadcast->total : 0 }}%"></div>
                </div>
                <div class="row text-center">
                    <div class="col"><div class="fs-4" id="total">{{ $broadcast->total }}</div><div class="small text-muted">{{ __('Total') }}</div></div>
                    <div class="col"><div class="fs-4 text-success" id="sent">{{ $broadcast->sent_count }}</div><div class="small text-muted">{{ __('Sent') }}</div></div>
                    <div class="col"><div class="fs-4 text-danger" id="failed">{{ $broadcast->failed_count }}</div><div class="small text-muted">{{ __('Failed') }}</div></div>
                    <div class="col"><div class="fs-4 text-secondary" id="pending">{{ max(0, $broadcast->total - $broadcast->sent_count - $broadcast->failed_count) }}</div><div class="small text-muted">{{ __('Waiting') }}</div></div>
                </div>
                <div class="small mt-3">
                    <i class="bi bi-speedometer2"></i> {!! __('Speed: :speed message(s)/second', ['speed' => '<b id="speed">'.e($broadcast->messages_per_second).'</b>']) !!}
                    @unless($broadcast->isFinished())
                        · {!! __('about :minutes minute(s) left', ['minutes' => '<b id="minutes-left">'.e($broadcast->estimatedMinutesRemaining()).'</b>']) !!}
                    @endunless
                </div>
                <div class="small text-muted mt-1" id="note">
                    @if($broadcast->status === 'paused')
                        {{ __('Paused — press Resume to continue sending.') }}
                    @elseif($broadcast->status === 'scheduled')
                        {{ __('Will start at :time (:tz).', ['time' => $broadcast->scheduled_at?->format('Y-m-d H:i'), 'tz' => config('app.timezone')]) }}
                    @elseif($broadcast->status === 'running' && $broadcast->next_batch_at?->isFuture())
                        {{ __('Pausing between batches — next batch at :time.', ['time' => $broadcast->next_batch_at->format('Y-m-d H:i')]) }}
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-5">{{ __('Content') }}</dt><dd class="col-7">{{ $broadcast->contentSummary() }}</dd>
                    <dt class="col-5">{{ __('Scheduled') }}</dt><dd class="col-7">{{ $broadcast->scheduled_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                    <dt class="col-5">{{ __('Started') }}</dt><dd class="col-7">{{ $broadcast->started_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                    <dt class="col-5">{{ __('Finished') }}</dt><dd class="col-7">{{ $broadcast->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</dd>
                    <dt class="col-5">{{ __('Batches') }}</dt><dd class="col-7">{{ __(':size per batch, :pause min pause', ['size' => $broadcast->batch_size, 'pause' => $broadcast->batch_delay_minutes]) }}</dd>
                    <dt class="col-5">{{ __('Created by') }}</dt><dd class="col-7">{{ $broadcast->creator?->username ?? '-' }}</dd>
                </dl>
                @if($broadcast->message)
                    <hr><div style="white-space: pre-wrap">{{ \Illuminate\Support\Str::limit($broadcast->message, 400) }}</div>
                @endif
                @if($broadcast->image_path)
                    <img src="{{ asset('storage/'.$broadcast->image_path) }}" class="img-fluid rounded mt-2" alt="">
                @endif
                @include('partials.button-preview', ['buttons' => $broadcast->buttons ?? []])
            </div>
        </div>
    </div>
    @unless($broadcast->isFinished())
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="mb-3"><i class="bi bi-sliders"></i> {{ __('Control speed') }} <span class="text-muted small fw-normal">{{ __('(takes effect within a few messages)') }}</span></h6>
                    <form method="POST" action="{{ route('broadcasts.speed', [$bot, $broadcast]) }}">
                        @csrf @method('PATCH')
                        @include('partials.broadcast-speed', [
                            'speed' => $broadcast->messages_per_second,
                            'batchSize' => $broadcast->batch_size,
                            'batchDelay' => $broadcast->batch_delay_minutes,
                            'audience' => max(0, $broadcast->total - $broadcast->sent_count - $broadcast->failed_count),
                        ])
                        <button class="btn btn-primary mt-3"><i class="bi bi-check-lg"></i> {{ __('Apply speed') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endunless
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <h5 class="mb-0">{{ __('Recipients') }}</h5>
    <div class="btn-group btn-group-sm">
        @foreach(['' => __('All'), 'sent' => __('Sent'), 'failed' => __('Failed'), 'pending' => __('Waiting')] as $value => $label)
            <a href="{{ route('broadcasts.show', [$bot, $broadcast, 'status' => $value ?: null]) }}"
               class="btn {{ request('status', '') === $value ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
        @endforeach
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>{{ __('Chat ID') }}</th><th>{{ __('Name') }}</th><th>{{ __('Status') }}</th><th>{{ __('Sent time') }}</th><th>{{ __('Message id') }}</th><th>{{ __('Error') }}</th></tr></thead>
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
                        @if($recipient->status === 'sent')<span class="badge text-bg-success">{{ __('sent') }}</span>
                        @elseif($recipient->status === 'failed')<span class="badge text-bg-danger">{{ __('failed') }}</span>
                        @else<span class="badge text-bg-secondary">{{ $broadcast->status === 'cancelled' ? __('not sent') : __('waiting') }}</span>@endif
                    </td>
                    <td class="small">{{ $recipient->sent_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                    <td class="small">{{ $recipient->telegram_message_id ?? '-' }}</td>
                    <td class="small text-danger">{{ $recipient->error }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">
                    {{ $broadcast->status === 'scheduled' ? __('The recipient list is created when the broadcast starts.') : __('No recipients.') }}
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
    const labels = @json($statusLabels);
    const t = @js([
        'paused' => __('Paused — press Resume to continue sending.'),
        'nextBatch' => __('Pausing between batches — next batch at :time.'),
        'sending' => __('Sending…'),
    ]);
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
            status.textContent = labels[data.status] || data.status;
            status.className = 'badge text-bg-' + (colors[data.status] || 'light');
            document.getElementById('bar-sent').style.width = (data.total ? data.sent * 100 / data.total : 0) + '%';
            document.getElementById('bar-failed').style.width = (data.total ? data.failed * 100 / data.total : 0) + '%';
            set('speed', data.messages_per_second);
            if (document.getElementById('minutes-left')) {
                set('minutes-left', data.minutes_left);
            }
            set('note', data.status === 'paused'
                ? t.paused
                : (data.next_batch_at && data.pending > 0
                    ? t.nextBatch.replace(':time', data.next_batch_at)
                    : (data.status === 'running' ? t.sending : '')));

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
