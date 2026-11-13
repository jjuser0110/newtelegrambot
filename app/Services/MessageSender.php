<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotReply;
use App\Models\TelegramMessage;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Storage;

/**
 * Sends text / image / buttons to a Telegram user and logs the outgoing message.
 */
class MessageSender
{
    public function sendReply(Bot $bot, TelegramUser $user, BotReply $reply): TelegramMessage
    {
        return $this->send($bot, $user, $reply->message, $reply->image_path, $reply->buttons ?? []);
    }

    /**
     * @param  array<int, array{text:string,type:string,value:string}>  $buttons
     */
    public function send(Bot $bot, TelegramUser $user, ?string $text, ?string $imagePath = null, array $buttons = []): TelegramMessage
    {
        $text = $text !== null ? trim($text) : '';
        $markup = $this->buildKeyboard($buttons);
        $api = $bot->api();

        try {
            if ($imagePath) {
                // Telegram caption limit is 1024 chars; send the rest as a separate message.
                $caption = mb_substr($text, 0, 1024);
                $rest = mb_substr($text, 1024);
                $result = $api->sendPhoto($user->chat_id, Storage::disk('public')->path($imagePath), $caption, $rest === '' ? $markup : null);
                if ($rest !== '') {
                    $api->sendMessage($user->chat_id, $rest, $markup);
                }
            } else {
                if ($text === '') {
                    $text = '-';
                }
                $result = $api->sendMessage($user->chat_id, $text, $markup);
            }
        } catch (TelegramException $e) {
            if ($e->getCode() === 403) {
                $user->update(['is_blocked' => true]);
            }
            throw $e;
        }

        if ($user->is_blocked) {
            $user->update(['is_blocked' => false]);
        }

        return TelegramMessage::create([
            'bot_id' => $bot->id,
            'telegram_user_id' => $user->id,
            'chat_id' => $user->chat_id,
            'direction' => 'out',
            'type' => $imagePath ? 'photo' : 'text',
            'message' => $text,
            'image_path' => $imagePath,
            'telegram_message_id' => $result['message_id'] ?? null,
            'payload' => $buttons ? ['buttons' => $buttons] : null,
        ]);
    }

    /**
     * One button per row. "url" buttons open a link, "callback" buttons trigger the reply whose trigger = value.
     */
    public function buildKeyboard(array $buttons): ?array
    {
        $rows = [];
        foreach ($buttons as $button) {
            $label = trim($button['text'] ?? '');
            $value = trim($button['value'] ?? '');
            if ($label === '' || $value === '') {
                continue;
            }
            $rows[] = [($button['type'] ?? 'url') === 'callback'
                ? ['text' => $label, 'callback_data' => mb_strcut($value, 0, 64)]
                : ['text' => $label, 'url' => $value]];
        }

        return $rows ? ['inline_keyboard' => $rows] : null;
    }
}
