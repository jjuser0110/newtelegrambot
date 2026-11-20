<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\TelegramUser;
use App\Services\MessageSender;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ChatController extends Controller
{
    /**
     * Tabs of the chat list.
     *
     * @var array<string, string>
     */
    public const TABS = [
        'starred' => 'Starred',
        'recent' => 'Recent',
        'old' => 'Old',
        'all' => 'All',
    ];

    /**
     * Choices for "a chat is old after N days without messages".
     *
     * @var array<int, int>
     */
    public const OLD_AFTER_DAYS = [1, 3, 7, 14, 30];

    public function index(Request $request, Bot $bot): View
    {
        $this->ownBot($bot);

        $tab = array_key_exists($request->tab, self::TABS) ? $request->tab : 'recent';
        $days = in_array($request->integer('days'), self::OLD_AFTER_DAYS, true) ? $request->integer('days') : 7;
        $cutoff = now()->subDays($days);

        $users = $this->applyTab($bot->telegramUsers(), $tab, $cutoff)
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('username', 'like', "%$s%")
                ->orWhere('first_name', 'like', "%$s%")
                ->orWhere('last_name', 'like', "%$s%")
                ->orWhere('chat_id', 'like', "%$s%")))
            ->withCount([
                'messages',
                'messages as unread_count' => fn ($query) => $query
                    ->where('direction', 'in')
                    ->whereColumn('telegram_messages.id', '>', 'telegram_users.last_read_message_id'),
            ])
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $tabCounts = collect(self::TABS)->map(fn ($label, $key) => $this->applyTab($bot->telegramUsers(), $key, $cutoff)->count());
        $latestMessageId = (int) $bot->messages()->max('id');

        return view('chats.index', compact('bot', 'users', 'latestMessageId', 'tab', 'days', 'tabCounts'));
    }

    /**
     * Star / un-star a chat so it shows in the "Starred" tab.
     */
    public function toggleStar(Request $request, Bot $bot, TelegramUser $telegramUser): JsonResponse|RedirectResponse
    {
        $this->ownBot($bot);

        $starred = $request->has('is_starred') ? $request->boolean('is_starred') : ! $telegramUser->is_starred;
        $telegramUser->update(['is_starred' => $starred]);

        if ($request->expectsJson()) {
            return response()->json(['is_starred' => $starred]);
        }

        return back()->with('success', $starred ? __('Chat starred.') : __('Star removed.'));
    }

    /**
     * Limit the chat list to one tab: starred, recent (message after $cutoff), old (no message since $cutoff) or all.
     *
     * @param  HasMany<TelegramUser, Bot>  $query
     * @return HasMany<TelegramUser, Bot>
     */
    private function applyTab(HasMany $query, string $tab, DateTimeInterface $cutoff): HasMany
    {
        return match ($tab) {
            'starred' => $query->where('is_starred', true),
            'recent' => $query->where('last_message_at', '>=', $cutoff),
            'old' => $query->where(fn ($old) => $old->where('last_message_at', '<', $cutoff)->orWhereNull('last_message_at')),
            default => $query,
        };
    }

    /**
     * Polled by the chat list: id of the newest message of this bot, so the page knows when to refresh.
     */
    public function latest(Bot $bot): JsonResponse
    {
        $this->ownBot($bot);

        return response()->json(['latest_message_id' => (int) $bot->messages()->max('id')]);
    }

    public function show(Bot $bot, TelegramUser $telegramUser): View
    {
        $this->ownBot($bot);
        $messages = $telegramUser->messages()->latest('id')->limit(200)->get()->reverse();
        $savedMessages = $bot->savedMessages()->latest()->get();
        $telegramUser->markReadUpTo((int) ($messages->last()?->id ?? 0));

        return view('chats.show', [
            'bot' => $bot,
            'tgUser' => $telegramUser,
            'messages' => $messages,
            'savedMessages' => $savedMessages,
            'savedKeys' => $this->savedKeys($bot),
            'lastMessageId' => (int) ($messages->last()?->id ?? 0),
        ]);
    }

    /**
     * Polled by the open chat: messages newer than ?after=id, rendered as HTML bubbles.
     */
    public function messages(Request $request, Bot $bot, TelegramUser $telegramUser): JsonResponse
    {
        $this->ownBot($bot);
        $after = $request->integer('after');

        $messages = $telegramUser->messages()->where('id', '>', $after)->orderBy('id')->limit(100)->get();
        $savedKeys = $this->savedKeys($bot);
        $telegramUser->refresh();

        // The chat is open on screen, so these new messages count as read.
        if ($messages->isNotEmpty()) {
            $telegramUser->markReadUpTo((int) $messages->last()->id);
        }

        return response()->json([
            'html' => $messages->map(fn ($message) => view('partials.chat-message', [
                'm' => $message,
                'bot' => $bot,
                'savedKeys' => $savedKeys,
            ])->render())->implode(''),
            'last_id' => (int) ($messages->last()?->id ?? $after),
            'new_incoming' => $messages->where('direction', 'in')->count(),
            'auto_reply' => $telegramUser->auto_reply,
            'is_blocked' => $telegramUser->is_blocked,
        ]);
    }

    /**
     * Turn the bot's auto-replies on/off for this one Telegram user (so a human can chat normally).
     */
    public function toggleAutoReply(Request $request, Bot $bot, TelegramUser $telegramUser): JsonResponse|RedirectResponse
    {
        $this->ownBot($bot);

        $enabled = $request->has('auto_reply') ? $request->boolean('auto_reply') : ! $telegramUser->auto_reply;
        $telegramUser->update(['auto_reply' => $enabled]);

        $message = $enabled ? __('Auto-reply is ON for this user.') : __('Auto-reply is OFF for this user — you are chatting manually.');

        if ($request->expectsJson()) {
            return response()->json(['auto_reply' => $enabled, 'message' => $message]);
        }

        return back()->with('success', $message);
    }

    public function send(Request $request, Bot $bot, TelegramUser $telegramUser, MessageSender $sender): JsonResponse|RedirectResponse
    {
        $this->ownBot($bot);

        $request->validate([
            'saved_message_id' => ['nullable', 'integer', Rule::exists('saved_messages', 'id')->where('bot_id', $bot->id)],
            'send_mode' => ['nullable', 'in:copy,forward'],
            'message' => ['nullable', 'string', 'max:4096', 'required_without_all:image,saved_message_id'],
            'image' => ['nullable', 'image', 'max:5120'],
            'buttons' => ['nullable', 'array', 'max:20'],
        ], ['message.required_without_all' => __('Please enter a message, choose an image or pick a saved message.')]);

        $buttons = ButtonInput::clean($request->input('buttons', []));

        try {
            if ($request->filled('saved_message_id')) {
                $saved = $bot->savedMessages()->findOrFail($request->saved_message_id);
                $sender->sendSaved($bot, $telegramUser, $saved, $request->input('send_mode', 'copy'), $buttons);
            } else {
                $image = $request->hasFile('image') ? $request->file('image')->store('sent', 'public') : null;
                $sender->send($bot, $telegramUser, $request->message, $image, $buttons);
            }
        } catch (Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('Send failed: :error', ['error' => $e->getMessage()])], 422);
            }

            return back()->withInput()->withErrors(['message' => __('Send failed: :error', ['error' => $e->getMessage()])]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Message sent.'));
    }

    /**
     * "chat_id:message_id" keys of messages already saved for reuse, to show "saved" instead of a Save button.
     *
     * @return Collection<string, int>
     */
    private function savedKeys(Bot $bot): Collection
    {
        return $bot->savedMessages()
            ->get(['from_chat_id', 'message_id'])
            ->map(fn ($saved) => $saved->from_chat_id.':'.$saved->message_id)
            ->flip();
    }
}
