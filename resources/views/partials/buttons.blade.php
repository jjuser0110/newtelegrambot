{{-- Dynamic inline-keyboard button rows. Expects $buttons (array). --}}
@php($rows = old('buttons', $buttons ?? []))
<label class="form-label">{{ __('Buttons') }} <span class="text-muted small">{{ __('(optional, shown under the message — 2 per row, an odd last button is full width)') }}</span></label>
<div id="button-rows">
    @foreach($rows as $i => $b)
        <div class="row g-2 mb-2 button-row">
            <div class="col-md-4"><input name="buttons[{{ $i }}][text]" value="{{ $b['text'] ?? '' }}" class="form-control" placeholder="{{ __('Button label') }}"></div>
            <div class="col-md-3">
                <select name="buttons[{{ $i }}][type]" class="form-select">
                    <option value="url" @selected(($b['type'] ?? 'url') === 'url')>{{ __('Open link') }}</option>
                    <option value="callback" @selected(($b['type'] ?? '') === 'callback')>{{ __('Send keyword reply') }}</option>
                </select>
            </div>
            <div class="col-md-4"><input name="buttons[{{ $i }}][value]" value="{{ $b['value'] ?? '' }}" class="form-control" placeholder="{{ __('https://... or keyword') }}"></div>
            <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-danger remove-row"><i class="bi bi-x-lg"></i></button></div>
        </div>
    @endforeach
</div>
<button type="button" class="btn btn-sm btn-outline-secondary" id="add-button-row"><i class="bi bi-plus"></i> {{ __('Add button') }}</button>
<div id="button-layout-preview" class="mt-2" style="max-width: 360px"></div>

<template id="button-row-template">
    <div class="row g-2 mb-2 button-row">
        <div class="col-md-4"><input name="buttons[__i__][text]" class="form-control" placeholder="{{ __('Button label') }}"></div>
        <div class="col-md-3">
            <select name="buttons[__i__][type]" class="form-select">
                <option value="url">{{ __('Open link') }}</option>
                <option value="callback">{{ __('Send keyword reply') }}</option>
            </select>
        </div>
        <div class="col-md-4"><input name="buttons[__i__][value]" class="form-control" placeholder="{{ __('https://... or keyword') }}"></div>
        <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-danger remove-row"><i class="bi bi-x-lg"></i></button></div>
    </div>
</template>

@push('scripts')
<script>
(() => {
    const wrap = document.getElementById('button-rows');
    let idx = {{ count($rows) }} + 1;
    const preview = document.getElementById('button-layout-preview');
    const previewTitle = @js(__('Preview in Telegram:'));
    const escapeHtml = (text) => text.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /** Show how Telegram will lay out the buttons: 2 per row, odd last one full width. */
    const renderPreview = () => {
        const labels = [...wrap.querySelectorAll('.button-row')]
            .map((row) => row.querySelector('input[name$="[text]"]').value.trim())
            .filter((label) => label !== '');
        if (labels.length === 0) {
            preview.innerHTML = '';
            return;
        }
        const cells = labels.map((label, i) => {
            const full = i === labels.length - 1 && labels.length % 2 === 1;
            return `<div class="${full ? 'col-12' : 'col-6'}"><div class="small text-center text-truncate rounded px-2 py-1 border border-primary text-primary bg-body">${escapeHtml(label)}</div></div>`;
        }).join('');
        preview.innerHTML = `<div class="small text-muted mb-1">${escapeHtml(previewTitle)}</div><div class="row g-1">${cells}</div>`;
    };

    document.getElementById('add-button-row').addEventListener('click', () => {
        const html = document.getElementById('button-row-template').innerHTML.replaceAll('__i__', idx++);
        wrap.insertAdjacentHTML('beforeend', html);
        renderPreview();
    });
    wrap.addEventListener('click', (e) => {
        const btn = e.target.closest('.remove-row');
        if (btn) {
            btn.closest('.button-row').remove();
            renderPreview();
        }
    });
    wrap.addEventListener('input', renderPreview);
    renderPreview();
})();
</script>
@endpush
