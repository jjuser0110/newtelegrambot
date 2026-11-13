<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['bot_id', 'chat_id', 'username', 'first_name', 'last_name', 'language_code', 'is_blocked', 'last_message_at'])]
class TelegramUser extends Model
{
    protected function casts(): array
    {
        return [
            'is_blocked' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    public function displayName(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        if ($name !== '') {
            return $name;
        }

        return $this->username ? '@'.$this->username : (string) $this->chat_id;
    }
}
