<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\TelegramUser;
use App\Services\MessageSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('chats.index', compact('bot', 'users'));
    }

    public function show(Bot $bot, TelegramUser $telegramUser): View
    {
        $this->ownBot($bot);
        $messages = $telegramUser->messages()->latest()->limit(200)->get()->reverse();
        $savedMessages = $bot->savedMessages()->latest()->get();
        $savedKeys = $savedMessages->map(fn ($saved) => $saved->from_chat_id.':'.$saved->message_id)->flip();

        return view('chats.show', [
            'bot' => $bot,
            'tgUser' => $telegramUser,
            'messages' => $messages,
            'savedMessages' => $savedMessages,
            'savedKeys' => $savedKeys,
        ]);
    }

    public function send(Request $request, Bot $bot, TelegramUser $telegramUser, MessageSender $sender): RedirectResponse
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
            return back()->withInput()->withErrors(['message' => 'Send failed: '.$e->getMessage()]);
        }

        return back()->with('success', 'Message sent.');
    }
}
