<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\TelegramUser;
use App\Services\MessageSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ChatController extends Controller
{
    public function index(Request $request, Bot $bot): View
    {
        $this->ownBot($bot);

        $users = $bot->telegramUsers()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('username', 'like', "%$s%")
                ->orWhere('first_name', 'like', "%$s%")
                ->orWhere('last_name', 'like', "%$s%")
                ->orWhere('chat_id', 'like', "%$s%")))
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->paginate(30)
            ->withQueryString();

        $latestMessageId = (int) $bot->messages()->max('id');

        return view('chats.index', compact('bot', 'users', 'latestMessageId'));
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

        $message = $enabled ? 'Auto-reply is ON for this user.' : 'Auto-reply is OFF for this user — you are chatting manually.';

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
        ], ['message.required_without_all' => 'Please enter a message, choose an image or pick a saved message.']);

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
                return response()->json(['message' => 'Send failed: '.$e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['message' => 'Send failed: '.$e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Message sent.');
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
