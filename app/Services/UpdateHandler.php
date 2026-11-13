<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\TelegramMessage;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes one Telegram update (from the webhook or from polling):
 * saves the chat_id / user details / message, then sends the configured auto-replies.
 */
class UpdateHandler
{
    public function __construct(private MessageSender $sender) {}

    private bool $autoReply = true;

    public function handle(Bot $bot, array $update, bool $autoReply = true): void
    {
        $this->autoReply = $autoReply;

        if (isset($update['message'])) {
            $this->handleMessage($bot, $update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->handleCallback($bot, $update['callback_query']);
        } elseif (isset($update['my_chat_member'])) {
            $this->handleMemberStatus($bot, $update['my_chat_member']);
        }
    }

    protected function handleMessage(Bot $bot, array $message): void
    {
        // Only private chats with users.
        if (($message['chat']['type'] ?? 'private') !== 'private') {
            return;
        }

        $user = $this->saveUser($bot, $message['chat'], $message['from'] ?? []);
        $text = $message['text'] ?? $message['caption'] ?? null;
        $type = isset($message['text']) ? 'text' : (isset($message['photo']) ? 'photo' : 'other');

        TelegramMessage::create([
            'bot_id' => $bot->id,
            'telegram_user_id' => $user->id,
            'chat_id' => $user->chat_id,
            'direction' => 'in',
            'type' => $type,
            'message' => $text,
            'telegram_message_id' => $message['message_id'] ?? null,
            'payload' => $message,
        ]);

        $this->autoReply($bot, $user, $text);
    }

    protected function handleCallback(Bot $bot, array $callback): void
    {
        $chat = $callback['message']['chat'] ?? ['id' => $callback['from']['id']];
        $user = $this->saveUser($bot, $chat, $callback['from'] ?? []);
        $data = $callback['data'] ?? '';

        TelegramMessage::create([
            'bot_id' => $bot->id,
            'telegram_user_id' => $user->id,
            'chat_id' => $user->chat_id,
            'direction' => 'in',
            'type' => 'callback',
            'message' => $data,
            'payload' => $callback,
        ]);

        try {
            $bot->api()->answerCallbackQuery($callback['id']);
        } catch (Throwable $e) {
            Log::warning('answerCallbackQuery failed: '.$e->getMessage());
        }

        $this->autoReply($bot, $user, $data, allowFallback: false);
    }

    protected function handleMemberStatus(Bot $bot, array $member): void
    {
        $chat = $member['chat'] ?? null;
        if (! $chat || ($chat['type'] ?? '') !== 'private') {
            return;
        }
        $status = $member['new_chat_member']['status'] ?? null;
        $user = $this->saveUser($bot, $chat, $member['from'] ?? [], touch: false);
        $user->update(['is_blocked' => $status === 'kicked']);
    }

    protected function saveUser(Bot $bot, array $chat, array $from, bool $touch = true): TelegramUser
    {
        $data = [
            'username' => $from['username'] ?? $chat['username'] ?? null,
            'first_name' => $from['first_name'] ?? $chat['first_name'] ?? null,
            'last_name' => $from['last_name'] ?? $chat['last_name'] ?? null,
            'language_code' => $from['language_code'] ?? null,
            'is_blocked' => false,
        ];
        if ($touch) {
            $data['last_message_at'] = now();
        }

        return TelegramUser::updateOrCreate(
            ['bot_id' => $bot->id, 'chat_id' => $chat['id']],
            $data
        );
    }

    /**
     * Send every active reply whose trigger matches the text. "/start" matches "/start anything".
     * If nothing matches, send the "*" fallback replies (if any).
     */
    protected function autoReply(Bot $bot, TelegramUser $user, ?string $text, bool $allowFallback = true): void
    {
        if (! $this->autoReply) {
            return;
        }

        $key = $this->normalize($text, $bot->username);
        $replies = $bot->replies()->where('is_active', true)->get();

        $matched = $key === ''
            ? collect()
            : $replies->filter(fn ($r) => mb_strtolower(trim($r->trigger)) === $key);

        if ($matched->isEmpty() && $allowFallback) {
            $matched = $replies->filter(fn ($r) => trim($r->trigger) === '*');
        }

        foreach ($matched as $reply) {
            try {
                $this->sender->sendReply($bot, $user, $reply);
            } catch (Throwable $e) {
                Log::error("Auto-reply #{$reply->id} failed for chat {$user->chat_id}: ".$e->getMessage());
            }
        }
    }

    protected function normalize(?string $text, ?string $botUsername): string
    {
        $text = mb_strtolower(trim((string) $text));
        if (str_starts_with($text, '/')) {
            // "/start payload" -> "/start", "/help@MyBot" -> "/help"
            $text = explode(' ', $text, 2)[0];
            if ($botUsername) {
                $text = str_replace('@'.mb_strtolower($botUsername), '', $text);
            }
        }

        return $text;
    }
}
