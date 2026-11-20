{{-- Preview of inline buttons as Telegram shows them: two per row, an odd last button full width.
     Expects $buttons (array of {text, type, value}) and optional $dark (on a blue bubble). --}}
@php
    $buttons = array_values($buttons ?? []);
    $count = count($buttons);
@endphp
@if($count > 0)
    <div class="row g-1 mt-1">
        @foreach($buttons as $index => $b)
            <div class="{{ $index === $count - 1 && $count % 2 === 1 ? 'col-12' : 'col-6' }}">
                <div class="small text-center text-truncate rounded px-2 py-1 border {{ ($dark ?? false) ? 'border-light' : 'border-primary text-primary bg-body' }}"
                     title="{{ ($b['type'] ?? 'url') === 'url' ? __('Opens :value', ['value' => $b['value'] ?? '']) : __('Sends reply: :value', ['value' => $b['value'] ?? '']) }}">
                    <i class="bi {{ ($b['type'] ?? 'url') === 'url' ? 'bi-box-arrow-up-right' : 'bi-reply' }}"></i> {{ $b['text'] ?? '' }}
                </div>
            </div>
        @endforeach
    </div>
@endif
