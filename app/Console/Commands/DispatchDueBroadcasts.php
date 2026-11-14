<?php

namespace App\Console\Commands;

use App\Jobs\SendBroadcastBatch;
use App\Models\Broadcast;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('broadcasts:dispatch-due')]
#[Description('Queue scheduled broadcasts that are due and the next batch of running broadcasts')]
class DispatchDueBroadcasts extends Command
{
    public function handle(): int
    {
        $due = Broadcast::query()
            ->where(fn ($query) => $query
                ->where(fn ($scheduled) => $scheduled
                    ->where('status', Broadcast::STATUS_SCHEDULED)
                    ->where('scheduled_at', '<=', now()))
                ->orWhere(fn ($running) => $running
                    ->where('status', Broadcast::STATUS_RUNNING)
                    ->where(fn ($next) => $next->whereNull('next_batch_at')->orWhere('next_batch_at', '<=', now()))))
            ->get();

        foreach ($due as $broadcast) {
            // SendBroadcastBatch is unique per broadcast, so a batch that is still queued/running is not duplicated.
            SendBroadcastBatch::dispatch($broadcast);
        }

        $this->info("Checked {$due->count()} due broadcast(s).");

        return self::SUCCESS;
    }
}
