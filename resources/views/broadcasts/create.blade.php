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
                        Messages are sent one by one in the background. Telegram allows about 30 messages per second per bot;
                        slower is safer. If Telegram still says "too many requests", the bot waits and halves the speed automatically.
                        For big lists you can also send in batches with a pause in between. You can change the speed or pause while it runs.
                    </p>
                    <div class="mb-4">
                        @include('partials.broadcast-speed', ['speed' => 15, 'batchSize' => 1000, 'batchDelay' => 10, 'audience' => $audience])
                    </div>

                    <button class="btn btn-primary" @disabled($audience === 0)><i class="bi bi-megaphone"></i> Create broadcast</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

