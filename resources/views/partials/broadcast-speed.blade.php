{{-- Speed controls for a broadcast. Expects $speed, $batchSize, $batchDelay and $audience (recipients for the estimate). --}}
@php
    $speed = old('messages_per_second', $speed);
    $batchSize = old('batch_size', $batchSize);
    $batchDelay = old('batch_delay_minutes', $batchDelay);
@endphp
<div class="broadcast-speed" data-audience="{{ $audience }}">
    <label class="form-label">Speed</label>
    <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach(\App\Models\Broadcast::SPEED_PRESETS as $perSecond => $label)
            <button type="button" class="btn btn-sm btn-outline-secondary speed-preset" data-speed="{{ $perSecond }}">{{ $label }}</button>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Messages per second (1–{{ \App\Models\Broadcast::MAX_MESSAGES_PER_SECOND }})</label>
            <input type="number" name="messages_per_second" min="1" max="{{ \App\Models\Broadcast::MAX_MESSAGES_PER_SECOND }}"
                   value="{{ $speed }}" class="form-control speed-input" required>
        </div>
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Batch size (messages, then pause)</label>
            <input type="number" name="batch_size" min="1" max="3000" value="{{ $batchSize }}" class="form-control batch-size-input" required>
        </div>
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Pause between batches (minutes)</label>
            <input type="number" name="batch_delay_minutes" min="0" max="1440" value="{{ $batchDelay }}" class="form-control batch-delay-input" required>
        </div>
    </div>
    <div class="small text-muted mt-2 speed-estimate"></div>
</div>

@pushOnce('scripts')
<script>
document.querySelectorAll('.broadcast-speed').forEach((wrap) => {
    const audience = parseInt(wrap.dataset.audience || '0', 10);
    const speed = wrap.querySelector('.speed-input');
    const size = wrap.querySelector('.batch-size-input');
    const delay = wrap.querySelector('.batch-delay-input');
    const out = wrap.querySelector('.speed-estimate');
    const presets = wrap.querySelectorAll('.speed-preset');

    const update = () => {
        const perSecond = Math.min(25, Math.max(1, parseInt(speed.value || '1', 10)));
        const batchSize = Math.max(1, parseInt(size.value || '1', 10));
        const pause = Math.max(0, parseInt(delay.value || '0', 10));
        const batches = Math.max(1, Math.ceil(audience / batchSize));
        const minutes = Math.ceil(audience / perSecond / 60) + (batches - 1) * pause;
        out.textContent = audience > 0
            ? `${audience} recipient(s) · ${perSecond}/second · ${batches} batch(es) · about ${minutes} minute(s) in total`
            : `${perSecond}/second · ${batchSize} per batch · ${pause} min pause`;
        presets.forEach((button) => {
            button.classList.toggle('active', parseInt(button.dataset.speed, 10) === perSecond);
        });
    };

    presets.forEach((button) => button.addEventListener('click', () => {
        speed.value = button.dataset.speed;
        update();
    }));
    [speed, size, delay].forEach((input) => input.addEventListener('input', update));
    update();
});
</script>
@endPushOnce
