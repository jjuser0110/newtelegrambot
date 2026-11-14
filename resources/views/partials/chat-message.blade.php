{{-- One chat bubble. Expects $m (TelegramMessage), $bot and $savedKeys. Also rendered for live updates. --}}
<div class="d-flex mb-2 {{ $m->direction === 'out' ? 'justify-content-end' : '' }}" data-message-id="{{ $m->id }}">
    <div class="bubble p-2 rounded shadow-sm {{ $m->direction === 'out' ? 'bg-primary text-white' : 'bg-white' }}">
        @if($m->image_path)
            <img src="{{ asset('storage/'.$m->image_path) }}" class="d-block mb-1" alt="">
        @elseif($m->type === 'photo' && $m->direction === 'in')
            <div class="fst-italic small"><i class="bi bi-image"></i> [photo]</div>
        @elseif($m->type === 'callback')
            <div class="fst-italic small"><i class="bi bi-hand-index"></i> pressed button:</div>
        @elseif($m->type === 'other')
            <div class="fst-italic small">[non-text message]</div>
        @elseif(in_array($m->type, ['copy', 'forward'], true))
            <div class="fst-italic small"><i class="bi bi-bookmark-star"></i> {{ $m->type === 'forward' ? 'Forwarded' : 'Copied' }} saved message: {{ $m->payload['saved_label'] ?? '' }}</div>
        @endif
        @if($m->message !== null && $m->message !== '')<div class="msg-text">{{ $m->message }}</div>@endif
        @foreach($m->payload['buttons'] ?? [] as $b)
            <div class="small border rounded px-2 mt-1">{{ $b['text'] }}</div>
        @endforeach
        <div class="small opacity-75 d-flex justify-content-between gap-3 mt-1">
            <span>
                @if($m->telegram_message_id)msg #{{ $m->telegram_message_id }}@endif
                @if($m->direction === 'in' && $m->telegram_message_id && $m->type !== 'callback')
                    @if(isset($savedKeys[$m->chat_id.':'.$m->telegram_message_id]))
                        · <span class="text-success"><i class="bi bi-bookmark-check"></i> saved</span>
                    @else
                        · <form method="POST" action="{{ route('bots.saved-messages.store', $bot) }}" class="d-inline save-message-form">
                            @csrf
                            <input type="hidden" name="telegram_message_id" value="{{ $m->id }}">
                            <input type="hidden" name="label" value="">
                            <button class="btn btn-link btn-sm p-0 align-baseline" title="Save to reuse as a reply or broadcast"><i class="bi bi-bookmark-plus"></i> Save</button>
                        </form>
                    @endif
                @endif
            </span>
            <span>{{ $m->created_at->format('Y-m-d H:i') }}</span>
        </div>
    </div>
</div>
