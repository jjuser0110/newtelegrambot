<?php

namespace App\Models;

use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'bot_id', 'created_by', 'title', 'message', 'image_path', 'photo_file_id', 'buttons', 'saved_message_id', 'send_mode',
    'status', 'scheduled_at', 'next_batch_at', 'started_at', 'finished_at', 'batch_size', 'batch_delay_minutes',
    'messages_per_second', 'total', 'sent_count', 'failed_count',
])]
class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    /**
     * Telegram allows about 30 messages/second per bot; stay safely below it.
     */
    public const MAX_MESSAGES_PER_SECOND = 25;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'buttons' => 'array',
            'scheduled_at' => 'datetime',
            'next_batch_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function savedMessage(): BelongsTo
    {
        return $this->belongsTo(SavedMessage::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED], true);
    }

    /**
     * Speed presets shown in the forms, in the current panel language (messages per second => label).
     *
     * @return array<int, string>
     */
    public static function speedPresets(): array
    {
        return [
            1 => __('Very slow – 1 / second (~3,600 per hour)'),
            5 => __('Slow – 5 / second (~18,000 per hour)'),
            15 => __('Normal – 15 / second (recommended)'),
            25 => __('Fast – 25 / second (maximum)'),
        ];
    }

    /**
     * Pause between two messages for the chosen speed.
     */
    public function sendIntervalMicroseconds(): int
    {
        $perSecond = max(1, min(self::MAX_MESSAGES_PER_SECOND, (int) $this->messages_per_second));

        return intdiv(1_000_000, $perSecond);
    }

    /**
     * Rough minutes left: sending time at the current speed plus the remaining pauses between batches.
     */
    public function estimatedMinutesRemaining(): int
    {
        $pending = max(0, $this->total - $this->sent_count - $this->failed_count);
        if ($pending === 0) {
            return 0;
        }

        $sendingSeconds = $pending * $this->sendIntervalMicroseconds() / 1_000_000;
        $remainingPauses = max(0, (int) ceil($pending / max(1, $this->batch_size)) - 1) * $this->batch_delay_minutes;

        return (int) ceil($sendingSeconds / 60) + $remainingPauses;
    }

    public function progressPercent(): int
    {
        if ($this->total === 0) {
            return $this->status === self::STATUS_COMPLETED ? 100 : 0;
        }

        return (int) floor(($this->sent_count + $this->failed_count) * 100 / $this->total);
    }

    public function contentSummary(): string
    {
        if ($this->savedMessage) {
            return $this->send_mode === 'forward'
                ? __('Forward saved message: :label', ['label' => $this->savedMessage->label])
                : __('Copy saved message: :label', ['label' => $this->savedMessage->label]);
        }

        return (string) ($this->title ?: str($this->message)->limit(60) ?: __('[image]'));
    }
}
