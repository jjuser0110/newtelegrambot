{{-- Pick a saved (customer / Premium-decorated) message to copy or forward instead of composing.
     Expects $savedMessages, $bot, optional $selectedId and $selectedMode. --}}
@php
    $selectedId = old('saved_message_id', $selectedId ?? null);
    $selectedMode = old('send_mode', $selectedMode ?? 'copy');
@endphp
<div class="border rounded p-3 bg-light saved-picker">
    <label class="form-label fw-semibold mb-1"><i class="bi bi-bookmark-star"></i> Use a saved message <span class="text-muted small fw-normal">(optional)</span></label>
    @if($savedMessages->isEmpty())
        <div class="small text-muted">No saved messages yet. Open a chat and click <b>Save</b> on a message someone sent to the bot,
            or add one on the <a href="{{ route('bots.saved-messages.index', $bot) }}">Saved messages</a> page.</div>
    @else
        <select name="saved_message_id" class="form-select mb-2 saved-select">
            <option value="">— No, compose the message myself —</option>
            @foreach($savedMessages as $saved)
                <option value="{{ $saved->id }}" @selected((string) $selectedId === (string) $saved->id)>
                    {{ $saved->label }}{{ $saved->has_custom_emoji ? ' ✨' : '' }} — {{ \Illuminate\Support\Str::limit($saved->preview ?? 'chat '.$saved->from_chat_id.' / msg '.$saved->message_id, 50) }}
                </option>
            @endforeach
        </select>
        <div class="d-flex flex-wrap gap-3 small">
            <div class="form-check">
                <input class="form-check-input" type="radio" name="send_mode" value="copy" id="mode-copy-{{ $uid = uniqid() }}" @checked($selectedMode !== 'forward')>
                <label class="form-check-label" for="mode-copy-{{ $uid }}"><b>Copy</b> – looks like the bot sent it (buttons allowed)</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="send_mode" value="forward" id="mode-fwd-{{ $uid }}" @checked($selectedMode === 'forward')>
                <label class="form-check-label" for="mode-fwd-{{ $uid }}"><b>Forward</b> – shows "Forwarded from", exactly as original (no buttons)</label>
            </div>
        </div>
        <div class="small text-muted mt-1">When a saved message is chosen, the message text and image fields below are ignored.</div>
    @endif
</div>

@pushOnce('scripts')
<script>
document.querySelectorAll('.saved-select').forEach((select) => {
    const form = select.closest('form');
    const toggle = () => {
        const useSaved = select.value !== '';
        form.querySelectorAll('.compose-only').forEach((el) => {
            el.style.opacity = useSaved ? '.4' : '1';
        });
    };
    select.addEventListener('change', toggle);
    toggle();
});
</script>
@endPushOnce
