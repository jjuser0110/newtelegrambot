@extends('layouts.app')
@section('title', 'New broadcast')

@section('content')
<nav class="mb-2"><a href="{{ route('broadcasts.index', $bot) }}">&larr; Broadcasts</a></nav>
<div class="row justify-content-center">
    <div class="col-lg-9">
        <h3 class="mb-1">New broadcast via &#64;{{ $bot->username }}</h3>
        <p class="text-muted">Currently <b>{{ $audience }}</b> Telegram users can receive it (users who blocked the bot are skipped).
            The list is taken at the moment the broadcast starts.</p>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('broadcasts.store', $bot) }}" enctype="multipart/form-data"
                      data-loading="Creating broadcast…">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-muted small">(optional, only for you)</span></label>
                        <input name="title" value="{{ old('title') }}" class="form-control" maxlength="100" placeholder="e.g. November promo">
                    </div>

                    <div class="mb-3">
                        @include('partials.saved-message-picker', ['savedMessages' => $savedMessages, 'bot' => $bot])
                    </div>

                    <div class="compose-only">
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" rows="6" class="form-control" maxlength="4096">{{ old('message') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image <span class="text-muted small">(optional — uploaded once, then reused)</span></label>
                            <input type="file" name="image" accept="image/*" class="form-control">
                        </div>
                    </div>

                    <div class="mb-4">@include('partials.buttons', ['buttons' => []])</div>

                    <h6 class="border-top pt-3">When to send</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Schedule date &amp; time <span class="text-muted small">(leave empty = send now)</span></label>
                            <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="form-control"
                                   min="{{ now()->format('Y-m-d\TH:i') }}">
                            <div class="form-text">Timezone: <b>{{ config('app.timezone') }}</b> (now {{ now()->format('Y-m-d H:i') }}).</div>
                        </div>
                    </div>

                    <h6 class="border-top pt-3">Sending speed</h6>
                    <p class="small text-muted mb-2">
                        Messages are sent in the background at about 20 per second (Telegram allows ~30/second per bot and
                        temporarily blocks bots that send too fast). If Telegram asks the bot to slow down, it waits automatically.
                        For big lists you can also send in batches with a pause in between.
                    </p>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Batch size</label>
                            <input type="number" name="batch_size" min="1" max="3000" value="{{ old('batch_size', 1000) }}" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Pause between batches (minutes)</label>
                            <input type="number" name="batch_delay_minutes" min="0" max="1440" value="{{ old('batch_delay_minutes', 10) }}" class="form-control" required>
                        </div>
                        <div class="col-md-4 small text-muted d-flex align-items-end" id="estimate"></div>
                    </div>

                    <button class="btn btn-primary" @disabled($audience === 0)><i class="bi bi-megaphone"></i> Create broadcast</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const audience = {{ $audience }};
    const size = document.querySelector('[name=batch_size]');
    const delay = document.querySelector('[name=batch_delay_minutes]');
    const out = document.getElementById('estimate');
    const update = () => {
        const batchSize = Math.max(1, parseInt(size.value || '1', 10));
        const batches = Math.max(1, Math.ceil(audience / batchSize));
        const minutes = Math.ceil(audience / 20 / 60) + (batches - 1) * Math.max(0, parseInt(delay.value || '0', 10));
        out.textContent = `${batches} batch(es) · about ${minutes} minute(s) in total`;
    };
    size.addEventListener('input', update);
    delay.addEventListener('input', update);
    update();
})();
</script>
@endpush
