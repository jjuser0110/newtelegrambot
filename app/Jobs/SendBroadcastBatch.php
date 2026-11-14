<?php

namespace App\Jobs;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\TelegramUser;
use App\Services\MessageSender;
use App\Services\TelegramException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends ONE batch (broadcast->batch_size recipients) of a broadcast, slowly enough to respect Telegram's limits.
 * When recipients remain, it sets next_batch_at; the scheduler (broadcasts:dispatch-due) dispatches the next batch.
 */
class SendBroadcastBatch implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Pause between two messages (microseconds). 50ms = max ~20 messages/second, below Telegram's ~30/second.
     */
    public const SEND_INTERVAL_MICROSECONDS = 50_000;

    private const MAX_RATE_LIMIT_RETRIES = 5;

    public int $timeout = 1500;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function __construct(public Broadcast $broadcast) {}

    public function uniqueId(): string
    {
        return (string) $this->broadcast->id;
    }

    public function handle(MessageSender $sender): void
    {
        $broadcast = Broadcast::with(['bot', 'savedMessage'])->find($this->broadcast->id);

        if (! $broadcast || $broadcast->isFinished()) {
            return;
        }

        if ($broadcast->status === Broadcast::STATUS_SCHEDULED) {
            if ($broadcast->scheduled_at && $broadcast->scheduled_at->isFuture()) {
                return;
            }
            if (! $this->start($broadcast)) {
                return;
            }
            $broadcast->refresh();
        }

        $recipients = $broadcast->recipients()
            ->where('status', 'pending')
            ->with('telegramUser')
            ->orderBy('id')
            ->limit($broadcast->batch_size)
            ->get();

        foreach ($recipients as $index => $recipient) {
            if ($index % 20 === 0 && $this->wasCancelled($broadcast)) {
                return;
            }

            $this->sendTo($broadcast, $recipient, $sender);
            usleep(self::SEND_INTERVAL_MICROSECONDS);
        }

        $remaining = $broadcast->recipients()->where('status', 'pending')->count();

        if ($this->wasCancelled($broadcast)) {
            return;
        }

        if ($remaining === 0) {
            $broadcast->update(['status' => Broadcast::STATUS_COMPLETED, 'finished_at' => now(), 'next_batch_at' => null]);

            return;
        }

        $broadcast->update(['next_batch_at' => now()->addMinutes($broadcast->batch_delay_minutes)]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Broadcast batch failed', ['broadcast_id' => $this->broadcast->id, 'exception' => $exception]);
    }

    /**
     * Switch scheduled -> running exactly once and snapshot the recipient list (every non-blocked user of the bot).
     */
    private function start(Broadcast $broadcast): bool
    {
        return DB::transaction(function () use ($broadcast): bool {
            $now = now();
            $updated = Broadcast::whereKey($broadcast->id)
                ->where('status', Broadcast::STATUS_SCHEDULED)
                ->update(['status' => Broadcast::STATUS_RUNNING, 'started_at' => $now, 'next_batch_at' => $now]);

            if ($updated === 0) {
                return false;
            }

            DB::table('broadcast_recipients')->insertUsing(
                ['broadcast_id', 'telegram_user_id', 'chat_id', 'status', 'created_at', 'updated_at'],
                TelegramUser::query()
                    ->where('bot_id', $broadcast->bot_id)
                    ->where('is_blocked', false)
                    ->selectRaw('?, id, chat_id, ?, ?, ?', [$broadcast->id, 'pending', $now, $now])
            );

            Broadcast::whereKey($broadcast->id)->update([
                'total' => BroadcastRecipient::where('broadcast_id', $broadcast->id)->count(),
            ]);

            return true;
        });
    }

    private function sendTo(Broadcast $broadcast, BroadcastRecipient $recipient, MessageSender $sender): void
    {
        $bot = $broadcast->bot;
        $user = $recipient->telegramUser;

        for ($attempt = 0; ; $attempt++) {
            try {
                if ($broadcast->savedMessage) {
                    $message = $sender->sendSaved($bot, $user, $broadcast->savedMessage, $broadcast->send_mode, $broadcast->buttons ?? []);
                } else {
                    $message = $sender->send($bot, $user, $broadcast->message, $broadcast->image_path, $broadcast->buttons ?? [], $broadcast->photo_file_id);

                    // Upload the image once, then reuse Telegram's file_id for everybody else.
                    if ($broadcast->image_path && ! $broadcast->photo_file_id && ! empty($message->payload['photo_file_id'])) {
                        $broadcast->update(['photo_file_id' => $message->payload['photo_file_id']]);
                    }
                }

                $recipient->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'telegram_message_id' => $message->telegram_message_id,
                    'error' => null,
                ]);
                $broadcast->increment('sent_count');

                return;
            } catch (TelegramException $e) {
                if ($e->retryAfter !== null && $attempt < self::MAX_RATE_LIMIT_RETRIES) {
                    // 429 Too Many Requests: wait as long as Telegram asks, then retry the same user.
                    sleep($e->retryAfter + 1);

                    continue;
                }
                $this->markFailed($broadcast, $recipient, $e->getMessage());

                return;
            } catch (Throwable $e) {
                $this->markFailed($broadcast, $recipient, $e->getMessage());

                return;
            }
        }
    }

    private function markFailed(Broadcast $broadcast, BroadcastRecipient $recipient, string $error): void
    {
        $recipient->update(['status' => 'failed', 'sent_at' => now(), 'error' => Str::limit($error, 250)]);
        $broadcast->increment('failed_count');
    }

    private function wasCancelled(Broadcast $broadcast): bool
    {
        return Broadcast::whereKey($broadcast->id)->value('status') === Broadcast::STATUS_CANCELLED;
    }
}
