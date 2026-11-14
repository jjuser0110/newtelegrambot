<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotReply;
use App\Models\SavedMessage;
use App\Models\TelegramMessage;
use App\Models\TelegramUser;
use Illuminate\Support\Facades\Storage;

/**
 * Sends text / image / buttons (or a saved message) to a Telegram user and logs the outgoing message.
 */
class MessageSender
{
    public function sendReply(Bot $bot, TelegramUser $user, BotReply $reply): TelegramMessage
    {
        if ($reply->savedMessage) {
            return $this->sendSaved($bot, $user, $reply->savedMessage, $reply->send_mode, $reply->buttons ?? []);
        }

        return $this->send($bot, $user, $reply->message, $reply->image_path, $reply->buttons ?? []);
    }

    /**
     * @param  array<int, array{text: string, type: string, value: string}>  $buttons
     * @param  string|null  $photoFileId  Telegram file_id of the same image uploaded before (skips re-uploading)
     */
    public function send(Bot $bot, TelegramUser $user, ?string $text, ?string $imagePath = null, array $buttons = [], ?string $photoFileId = null): TelegramMessage
    {
        $text = $text !== null ? trim($text) : '';
        $markup = $this->buildKeyboard($buttons);
        $api = $bot->api();

        $result = $this->guard($user, function () use ($api, $user, $text, $imagePath, $markup, $photoFileId): array {
            if ($imagePath) {
                // Telegram caption limit is 1024 chars; send the rest as a separate message.
                $caption = mb_substr($text, 0, 1024);
                $rest = mb_substr($text, 1024);
                $result = $api->sendPhoto($user->chat_id, Storage::disk('public')->path($imagePath), $caption, $rest === '' ? $markup : null, $photoFileId);
                if ($rest !== '') {
                    $api->sendMessage($user->chat_id, $rest, $markup);
                }

                return $result;
            }

            return $api->sendMessage($user->chat_id, $text === '' ? '-' : $text, $markup);
        });

        $payload = $buttons ? ['buttons' => $buttons] : [];
        if ($imagePath && ! empty($result['photo'])) {
            $payload['photo_file_id'] = end($result['photo'])['file_id'] ?? null;
        }

        return $this->log($bot, $user, [
            'type' => $imagePath ? 'photo' : 'text',
            'message' => $text,
            'image_path' => $imagePath,
            'telegram_message_id' => $result['message_id'] ?? null,
            'payload' => $payload ?: null,
        ]);
    }

    /**
     * Copy (looks like the bot wrote it) or forward (shows "Forwarded from") a saved message.
     *
     * @param  array<int, array{text: string, type: string, value: string}>  $buttons  only used in copy mode
     */
    public function sendSaved(Bot $bot, TelegramUser $user, SavedMessage $saved, string $mode = 'copy', array $buttons = []): TelegramMessage
    {
        $api = $bot->api();

        $result = $this->guard($user, fn (): array => $mode === 'forward'
            ? $api->forwardMessage($user->chat_id, $saved->from_chat_id, (int) $saved->message_id)
            : $api->copyMessage($user->chat_id, $saved->from_chat_id, (int) $saved->message_id, $this->buildKeyboard($buttons)));

        return $this->log($bot, $user, [
            'type' => $mode === 'forward' ? 'forward' : 'copy',
            'message' => $saved->preview,
            'telegram_message_id' => $result['message_id'] ?? null,
            'payload' => array_filter([
                'saved_message_id' => $saved->id,
                'saved_label' => $saved->label,
                'buttons' => $mode === 'forward' ? null : ($buttons ?: null),
            ]),
        ]);
    }

    /**
     * One button per row. "url" buttons open a link, "callback" buttons trigger the reply whose trigger = value.
     *
     * @param  array<int, array{text?: string, type?: string, value?: string}>  $buttons
     * @return array{inline_keyboard: array<int, array<int, array<string, string>>>}|null
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

    /**
     * Run a Telegram call and keep the user's "blocked" flag in sync with the result.
     *
     * @param  callable(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function guard(TelegramUser $user, callable $callback): array
    {
        try {
            $result = $callback();
        } catch (TelegramException $e) {
            if ($e->getCode() === 403) {
                $user->update(['is_blocked' => true]);
            }
            throw $e;
        }

        if ($user->is_blocked) {
            $user->update(['is_blocked' => false]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function log(Bot $bot, TelegramUser $user, array $attributes): TelegramMessage
    {
        return TelegramMessage::create($attributes + [
            'bot_id' => $bot->id,
            'telegram_user_id' => $user->id,
            'chat_id' => $user->chat_id,
            'direction' => 'out',
        ]);
    }
}
