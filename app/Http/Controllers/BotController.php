<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\User;
use App\Services\TelegramApi;
use App\Services\TelegramException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class BotController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSuperadmin()) {
            $companies = User::where('role', User::ROLE_COMPANY)->orderBy('name')->get(['id', 'name', 'username']);
            $bots = Bot::with('user:id,name,username')
                ->withCount(['replies', 'telegramUsers'])
                ->when($request->company, fn ($query, $companyId) => $query->where('user_id', $companyId))
                ->latest()
                ->get();

            return view('bots.index', compact('bots', 'user', 'companies'));
        }

        $bots = $user->bots()->withCount(['replies', 'telegramUsers'])->latest()->get();

        return view('bots.index', compact('bots', 'user'));
    }

    /** "Connect" – validate the token with getMe, save the bot and register the webhook. */
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'token' => ['required', 'string', 'regex:/^\d+:[A-Za-z0-9_-]{30,}$/'],
        ], ['token.regex' => 'This does not look like a Telegram bot token (e.g. 123456789:ABCdef...).']);

        if (! $user->canAddBot()) {
            return back()->withErrors(['token' => "You have reached your bot limit ({$user->bot_limit}). Please contact the administrator."]);
        }

        $token = trim($request->token);

        try {
            $me = (new TelegramApi($token))->getMe();
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['token' => 'Could not connect to Telegram: '.$e->getMessage()]);
        }

        if (Bot::where('telegram_bot_id', $me['id'])->exists()) {
            return back()->withInput()->withErrors(['token' => 'This bot is already connected to an account.']);
        }

        $bot = $user->bots()->create([
            'token' => $token,
            'telegram_bot_id' => $me['id'],
            'username' => $me['username'] ?? null,
            'first_name' => $me['first_name'] ?? null,
            'webhook_secret' => Str::random(48),
        ]);

        // Default /start reply so the bot answers immediately.
        $bot->replies()->create([
            'trigger' => '/start',
            'message' => 'Hello! Welcome to '.($me['first_name'] ?? 'our bot').'.',
        ]);

        [$ok, $msg] = $this->registerWebhook($bot);

        return redirect()->route('bots.show', $bot)
            ->with($ok ? 'success' : 'warning', 'Bot @'.$bot->username.' connected. '.$msg);
    }

    public function show(Bot $bot)
    {
        $this->ownBot($bot);
        $bot->loadCount(['telegramUsers', 'messages']);
        $replies = $bot->replies()->with('savedMessage')->get();

        return view('bots.show', compact('bot', 'replies'));
    }

    public function webhook(Bot $bot)
    {
        $this->ownBot($bot);
        [$ok, $msg] = $this->registerWebhook($bot);

        return back()->with($ok ? 'success' : 'warning', $msg);
    }

    public function toggle(Bot $bot)
    {
        $this->ownBot($bot)->update(['is_active' => ! $bot->is_active]);

        return back()->with('success', $bot->is_active ? 'Bot enabled.' : 'Bot paused (incoming messages are still saved, no auto-replies).');
    }

    public function destroy(Bot $bot)
    {
        $this->ownBot($bot);

        try {
            $bot->api()->deleteWebhook();
        } catch (Throwable) {
            // token might be revoked already
        }
        $bot->delete();

        return redirect()->route('bots.index')->with('success', 'Bot disconnected.');
    }

    /** @return array{0:bool,1:string} */
    private function registerWebhook(Bot $bot): array
    {
        $url = $bot->webhookUrl();

        if (! str_starts_with($url, 'https://')) {
            $bot->update(['webhook_set' => false]);

            return [false, 'Webhook not set: Telegram requires a public HTTPS APP_URL (current: '.config('app.url').'). For local testing run "php artisan telegram:poll".'];
        }

        try {
            $bot->api()->setWebhook($url, $bot->webhook_secret);
            $bot->update(['webhook_set' => true]);

            return [true, 'Webhook set to '.$url];
        } catch (TelegramException $e) {
            $bot->update(['webhook_set' => false]);

            return [false, 'Webhook failed: '.$e->getMessage()];
        }
    }
}
