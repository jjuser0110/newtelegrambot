<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around the Telegram Bot API for one bot token.
 */
class TelegramApi
{
    public function __construct(private string $token) {}

    protected function http(): PendingRequest
    {
        return Http::timeout(30)->connectTimeout(10)->acceptJson();
    }

    protected function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.$this->token.'/'.$method;
    }

    /**
     * @return mixed the "result" field of the Telegram response
     */
    public function call(string $method, array $params = []): mixed
    {
        $response = $this->http()->post($this->url($method), $params);

        return $this->parse($response->json(), $response->status());
    }

    protected function parse(?array $json, int $status): mixed
    {
        if (! is_array($json) || empty($json['ok'])) {
            throw new TelegramException(
                $json['description'] ?? 'Telegram API request failed (HTTP '.$status.')',
                (int) ($json['error_code'] ?? $status)
            );
        }

        return $json['result'] ?? null;
    }

    public function getMe(): array
    {
        return $this->call('getMe');
    }

    public function setWebhook(string $url, string $secret): mixed
    {
        return $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query', 'my_chat_member'],
            'drop_pending_updates' => false,
        ]);
    }

    public function deleteWebhook(): mixed
    {
        return $this->call('deleteWebhook');
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }

    public function getUpdates(int $offset = 0, int $timeout = 0): array
    {
        $response = Http::timeout($timeout + 15)->acceptJson()->post($this->url('getUpdates'), [
            'offset' => $offset,
            'timeout' => $timeout,
            'allowed_updates' => ['message', 'callback_query', 'my_chat_member'],
        ]);

        return $this->parse($response->json(), $response->status());
    }

    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): array
    {
        $params = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->call('sendMessage', $params);
    }

    /**
     * Upload a local image file and send it as a photo.
     */
    public function sendPhoto(int|string $chatId, string $localPath, ?string $caption = null, ?array $replyMarkup = null): array
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('Image file not found: '.$localPath);
        }

        $params = ['chat_id' => (string) $chatId];
        if ($caption !== null && $caption !== '') {
            $params['caption'] = $caption;
        }
        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        $response = $this->http()
            ->attach('photo', file_get_contents($localPath), basename($localPath))
            ->post($this->url('sendPhoto'), $params);

        return $this->parse($response->json(), $response->status());
    }

    public function answerCallbackQuery(string $callbackQueryId): mixed
    {
        return $this->call('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
    }
}
