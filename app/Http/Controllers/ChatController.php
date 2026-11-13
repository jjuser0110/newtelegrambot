<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\TelegramUser;
use App\Services\MessageSender;
use Illuminate\Http\Request;
use Throwable;

class ChatController extends Controller
{
    public function index(Request $request, Bot $bot)
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

    public function show(Bot $bot, TelegramUser $telegramUser)
    {
        $this->ownBot($bot);
        $messages = $telegramUser->messages()->latest()->limit(200)->get()->reverse();

        return view('chats.show', ['bot' => $bot, 'tgUser' => $telegramUser, 'messages' => $messages]);
    }

    public function send(Request $request, Bot $bot, TelegramUser $telegramUser, MessageSender $sender)
    {
        $this->ownBot($bot);
        [$text, $image, $buttons] = $this->validatedMessage($request);

        try {
            $sender->send($bot, $telegramUser, $text, $image, $buttons);
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['message' => 'Send failed: '.$e->getMessage()]);
        }

        return back()->with('success', 'Message sent.');
    }

    public function broadcastForm(Bot $bot)
    {
        $this->ownBot($bot);
        $count = $bot->telegramUsers()->where('is_blocked', false)->count();

        return view('chats.broadcast', compact('bot', 'count'));
    }

    public function broadcast(Request $request, Bot $bot, MessageSender $sender)
    {
        $this->ownBot($bot);
        [$text, $image, $buttons] = $this->validatedMessage($request);

        @set_time_limit(0);
        $sent = $failed = 0;

        $bot->telegramUsers()->where('is_blocked', false)->chunkById(100, function ($users) use ($sender, $bot, $text, $image, $buttons, &$sent, &$failed) {
            foreach ($users as $user) {
                try {
                    $sender->send($bot, $user, $text, $image, $buttons);
                    $sent++;
                } catch (Throwable) {
                    $failed++;
                }
                usleep(40_000); // stay under Telegram's ~30 msg/sec limit
            }
        });

        return redirect()->route('chats.index', $bot)->with('success', "Broadcast finished: $sent sent, $failed failed.");
    }

    /** @return array{0:?string,1:?string,2:array} */
    private function validatedMessage(Request $request): array
    {
        $request->validate([
            'message' => ['nullable', 'string', 'max:4096', 'required_without:image'],
            'image' => ['nullable', 'image', 'max:5120'],
            'buttons' => ['nullable', 'array', 'max:20'],
        ], ['message.required_without' => 'Please enter a message or choose an image.']);

        $buttons = ButtonInput::clean($request->input('buttons', []));
        $image = $request->hasFile('image') ? $request->file('image')->store('sent', 'public') : null;

        return [$request->message, $image, $buttons];
    }
}
