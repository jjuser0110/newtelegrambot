<?php

namespace App\Console\Commands;

use App\Models\Bot;
use App\Services\UpdateHandler;
use Illuminate\Console\Command;
use Throwable;

/**
 * Local-development alternative to the webhook: fetches updates with getUpdates.
 * Telegram cannot reach http://*.test, so run this while testing on Laragon.
 */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll {--bot= : Only poll this bot id} {--once : Fetch once and exit}';

    protected $description = 'Poll Telegram for new messages (use locally when no public HTTPS webhook is available)';

    public function handle(UpdateHandler $handler): int
    {
        $this->info('Polling Telegram... press Ctrl+C to stop.');
        $webhookCleared = [];

        do {
            $bots = Bot::with('user')
                ->when($this->option('bot'), fn ($q, $id) => $q->whereKey($id))
                ->get();

            if ($bots->isEmpty()) {
                $this->warn('No bots connected yet.');
            }

            foreach ($bots as $bot) {
                try {
                    // getUpdates does not work while a webhook is set.
                    if (! isset($webhookCleared[$bot->id])) {
                        $bot->api()->deleteWebhook();
                        $bot->update(['webhook_set' => false]);
                        $webhookCleared[$bot->id] = true;
                    }

                    $updates = $bot->api()->getUpdates($bot->last_update_id + 1, $bots->count() === 1 ? 25 : 0);

                    foreach ($updates as $update) {
                        $this->line("[@{$bot->username}] update {$update['update_id']}");
                        try {
                            $handler->handle($bot, $update, autoReply: $bot->is_active && $bot->user->is_active);
                        } catch (Throwable $e) {
                            $this->error('  handler error: '.$e->getMessage());
                        }
                        $bot->update(['last_update_id' => $update['update_id']]);
                    }
                } catch (Throwable $e) {
                    $this->error("[@{$bot->username}] ".$e->getMessage());
                }
            }

            if (! $this->option('once') && $bots->count() !== 1) {
                sleep(2);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }
}
