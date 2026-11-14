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
    'total', 'sent_count', 'failed_count',
])]
class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RUNNING = 'running';

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
            return ucfirst($this->send_mode).' saved message: '.$this->savedMessage->label;
        }

        return (string) ($this->title ?: str($this->message)->limit(60) ?: '[image]');
    }
}
