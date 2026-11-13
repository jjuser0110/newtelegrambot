{{-- Dynamic inline-keyboard button rows. Expects $buttons (array). --}}
@php($rows = old('buttons', $buttons ?? []))
<label class="form-label">Buttons <span class="text-muted small">(optional, shown under the message)</span></label>
<div id="button-rows">
    @foreach($rows as $i => $b)
        <div class="row g-2 mb-2 button-row">
            <div class="col-md-4"><input name="buttons[{{ $i }}][text]" value="{{ $b['text'] ?? '' }}" class="form-control" placeholder="Button label"></div>
            <div class="col-md-3">
                <select name="buttons[{{ $i }}][type]" class="form-select">
                    <option value="url" @selected(($b['type'] ?? 'url') === 'url')>Open link</option>
                    <option value="callback" @selected(($b['type'] ?? '') === 'callback')>Send keyword reply</option>
                </select>
            </div>
            <div class="col-md-4"><input name="buttons[{{ $i }}][value]" value="{{ $b['value'] ?? '' }}" class="form-control" placeholder="https://... or keyword"></div>
            <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-danger remove-row"><i class="bi bi-x-lg"></i></button></div>
        </div>
    @endforeach
</div>
<button type="button" class="btn btn-sm btn-outline-secondary" id="add-button-row"><i class="bi bi-plus"></i> Add button</button>

<template id="button-row-template">
    <div class="row g-2 mb-2 button-row">
        <div class="col-md-4"><input name="buttons[__i__][text]" class="form-control" placeholder="Button label"></div>
        <div class="col-md-3">
            <select name="buttons[__i__][type]" class="form-select">
                <option value="url">Open link</option>
                <option value="callback">Send keyword reply</option>
            </select>
        </div>
        <div class="col-md-4"><input name="buttons[__i__][value]" class="form-control" placeholder="https://... or keyword"></div>
        <div class="col-md-1 d-grid"><button type="button" class="btn btn-outline-danger remove-row"><i class="bi bi-x-lg"></i></button></div>
    </div>
</template>

@push('scripts')
<script>
(() => {
    const wrap = document.getElementById('button-rows');
    let idx = {{ count($rows) }} + 1;
    document.getElementById('add-button-row').addEventListener('click', () => {
        const html = document.getElementById('button-row-template').innerHTML.replaceAll('__i__', idx++);
        wrap.insertAdjacentHTML('beforeend', html);
    });
    wrap.addEventListener('click', e => {
        const btn = e.target.closest('.remove-row');
        if (btn) btn.closest('.button-row').remove();
    });
})();
</script>
@endpush
