@extends('layouts.app')
@section('title', $tgUser->displayName())

@section('content')
<nav class="mb-2"><a href="{{ route('chats.index', $bot) }}">&larr; {{ __('All chats') }}</a></nav>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-body d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <button type="button" id="star-button" class="btn btn-link p-0 fs-5 align-baseline {{ $tgUser->is_starred ? 'text-warning' : 'text-secondary' }}"
                            title="{{ __('Star = important chat (shows in the Starred tab)') }}">
                        <i class="bi {{ $tgUser->is_starred ? 'bi-star-fill' : 'bi-star' }}"></i>
                    </button>
                    <b>{{ $tgUser->displayName() }}</b>
                    @if($tgUser->username)<span class="text-muted">&#64;{{ $tgUser->username }}</span>@endif
                    <span id="blocked-badge" class="badge text-bg-danger {{ $tgUser->is_blocked ? '' : 'd-none' }}">{{ __('blocked bot') }}</span>
                    <span class="badge text-bg-body-tertiary border ms-1" title="{{ __('New messages appear automatically') }}">
                        <span class="spinner-grow spinner-grow-sm text-success" style="width: .5rem; height: .5rem;"></span> {{ __('live') }}
                    </span>
                </div>
                <div class="form-check form-switch mb-0" title="{{ __('When OFF, the bot does not auto-reply to this user, so you can chat with them yourself.') }}">
                    <input class="form-check-input" type="checkbox" role="switch" id="auto-reply-switch" @checked($tgUser->auto_reply)>
                    <label class="form-check-label small" for="auto-reply-switch" id="auto-reply-label">
                        {{ $tgUser->auto_reply ? __('Auto-reply ON') : __('Auto-reply OFF (manual chat)') }}
                    </label>
                </div>
            </div>
            <div class="card-body chat-box" id="chat-box">
                <div id="chat-empty" class="text-muted text-center {{ $messages->isEmpty() ? '' : 'd-none' }}">{{ __('No messages.') }}</div>
                @foreach($messages as $m)
                    @include('partials.chat-message', ['m' => $m, 'bot' => $bot, 'savedKeys' => $savedKeys])
                @endforeach
            </div>
            <div class="card-footer bg-body">
                <div id="send-error" class="alert alert-danger py-2 small d-none"></div>
                <form method="POST" action="{{ route('chats.send', [$bot, $tgUser]) }}" enctype="multipart/form-data" id="send-form">
                    @csrf
                    <div class="compose-only">
                        <textarea name="message" rows="3" class="form-control mb-2" id="message-input"
                                  placeholder="{{ __('Type a message to send via :bot (Enter to send, Shift+Enter for a new line)', ['bot' => '@'.$bot->username]) }}" maxlength="4096">{{ old('message') }}</textarea>
                        <input type="file" name="image" accept="image/*" class="form-control mb-2">
                    </div>
                    <details class="mb-2" @if($savedMessages->isNotEmpty() && old('saved_message_id')) open @endif>
                        <summary class="small text-muted">{{ __('Reply with a saved message (copy / forward)') }}</summary>
                        <div class="mt-2">@include('partials.saved-message-picker', ['savedMessages' => $savedMessages, 'bot' => $bot])</div>
                    </details>
                    <details class="mb-2">
                        <summary class="small text-muted">{{ __('Add buttons') }}</summary>
                        <div class="mt-2">@include('partials.buttons', ['buttons' => []])</div>
                    </details>
                    <button class="btn btn-primary" id="send-button"><i class="bi bi-send"></i> {{ __('Send') }}</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body small">
                <h6>{{ __('User details') }}</h6>
                <dl class="row mb-0">
                    <dt class="col-5">{{ __('Chat ID') }}</dt><dd class="col-7"><code>{{ $tgUser->chat_id }}</code></dd>
                    <dt class="col-5">{{ __('First name') }}</dt><dd class="col-7">{{ $tgUser->first_name ?? '-' }}</dd>
                    <dt class="col-5">{{ __('Last name') }}</dt><dd class="col-7">{{ $tgUser->last_name ?? '-' }}</dd>
                    <dt class="col-5">{{ __('Username') }}</dt><dd class="col-7">{{ $tgUser->username ? '@'.$tgUser->username : '-' }}</dd>
                    <dt class="col-5">{{ __('Language') }}</dt><dd class="col-7">{{ $tgUser->language_code ?? '-' }}</dd>
                    <dt class="col-5">{{ __('First seen') }}</dt><dd class="col-7">{{ $tgUser->created_at->format('Y-m-d H:i') }}</dd>
                    <dt class="col-5">{{ __('Last message') }}</dt><dd class="col-7">{{ $tgUser->last_message_at?->format('Y-m-d H:i') ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const t = @js([
        'autoOn' => __('Auto-reply ON'),
        'autoOff' => __('Auto-reply OFF (manual chat)'),
        'newMessage' => __('New message'),
        'sending' => __('Sending…'),
        'send' => __('Send'),
        'sendFailed' => __('Send failed.'),
        'networkError' => __('Network error, message not sent.'),
        'saveLabel' => __('Name for this saved message (e.g. "Promo Nov"):'),
    ]);
    const box = document.getElementById('chat-box');
    const form = document.getElementById('send-form');
    const input = document.getElementById('message-input');
    const sendButton = document.getElementById('send-button');
    const errorBox = document.getElementById('send-error');
    const autoSwitch = document.getElementById('auto-reply-switch');
    const autoLabel = document.getElementById('auto-reply-label');
    const csrf = form.querySelector('[name=_token]').value;
    const messagesUrl = @json(route('chats.messages', [$bot, $tgUser]));
    const autoReplyUrl = @json(route('chats.auto-reply', [$bot, $tgUser]));
    const baseTitle = document.title;
    let lastId = {{ $lastMessageId }};
    let unread = 0;
    let polling = false;
    let pollAgain = false;

    const scrollToBottom = () => { box.scrollTop = box.scrollHeight; };
    const isNearBottom = () => box.scrollHeight - box.scrollTop - box.clientHeight < 120;
    scrollToBottom();

    const setAutoReply = (enabled) => {
        autoSwitch.checked = enabled;
        autoLabel.textContent = enabled ? t.autoOn : t.autoOff;
    };

    /** Fetch messages newer than lastId and append them. */
    const poll = async () => {
        if (polling) {
            pollAgain = true;
            return;
        }
        polling = true;
        try {
            const response = await fetch(`${messagesUrl}?after=${lastId}`, { headers: { 'Accept': 'application/json' } });
            if (response.ok) {
                const data = await response.json();
                if (data.html) {
                    const stick = isNearBottom();
                    box.insertAdjacentHTML('beforeend', data.html);
                    document.getElementById('chat-empty').classList.add('d-none');
                    if (stick) {
                        scrollToBottom();
                    }
                    if (document.hidden && data.new_incoming > 0) {
                        unread += data.new_incoming;
                        document.title = `(${unread}) ${t.newMessage} · ${baseTitle}`;
                    }
                }
                lastId = data.last_id;
                setAutoReply(data.auto_reply);
                document.getElementById('blocked-badge').classList.toggle('d-none', !data.is_blocked);
            }
        } catch (e) {
            // network hiccup: try again on the next tick
        } finally {
            polling = false;
            if (pollAgain) {
                pollAgain = false;
                poll();
            }
        }
    };
    setInterval(poll, 3000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            unread = 0;
            document.title = baseTitle;
            poll();
        }
    });

    /** Send without reloading the page. */
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorBox.classList.add('d-none');
        sendButton.disabled = true;
        sendButton.innerHTML = `<span class="spinner-border spinner-border-sm"></span> ${t.sending}`;
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const errors = data.errors ? Object.values(data.errors).flat() : [data.message || t.sendFailed];
                errorBox.textContent = errors.join(' ');
                errorBox.classList.remove('d-none');
                return;
            }
            input.value = '';
            form.querySelector('[name=image]').value = '';
            const savedSelect = form.querySelector('[name=saved_message_id]');
            if (savedSelect) {
                savedSelect.value = '';
                savedSelect.dispatchEvent(new Event('change'));
            }
            await poll();
            scrollToBottom();
        } catch (e) {
            errorBox.textContent = t.networkError;
            errorBox.classList.remove('d-none');
        } finally {
            sendButton.disabled = false;
            sendButton.innerHTML = `<i class="bi bi-send"></i> ${t.send}`;
            input.focus();
        }
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    /** Per-user auto-reply switch. */
    autoSwitch.addEventListener('change', async () => {
        const wanted = autoSwitch.checked;
        try {
            const response = await fetch(autoReplyUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ auto_reply: wanted }),
            });
            const data = await response.json();
            setAutoReply(data.auto_reply);
        } catch (e) {
            setAutoReply(!wanted);
        }
    });

    /** Star / un-star this chat. */
    const starButton = document.getElementById('star-button');
    let starred = @json($tgUser->is_starred);
    const showStar = () => {
        starButton.classList.toggle('text-warning', starred);
        starButton.classList.toggle('text-secondary', !starred);
        starButton.innerHTML = `<i class="bi ${starred ? 'bi-star-fill' : 'bi-star'}"></i>`;
    };
    starButton.addEventListener('click', async () => {
        try {
            const response = await fetch(@json(route('chats.star', [$bot, $tgUser])), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ is_starred: !starred }),
            });
            starred = (await response.json()).is_starred;
            showStar();
        } catch (e) {
            // leave as it was
        }
    });

    /** "Save" buttons on bubbles (also on bubbles added live). */
    box.addEventListener('submit', (event) => {
        const saveForm = event.target.closest('.save-message-form');
        if (!saveForm) {
            return;
        }
        const label = prompt(t.saveLabel, '');
        if (label === null) {
            event.preventDefault();
            return;
        }
        saveForm.querySelector('[name=label]').value = label;
    });
})();
</script>
@endpush
