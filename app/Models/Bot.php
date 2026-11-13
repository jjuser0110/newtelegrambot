<?php

namespace App\Models;

use App\Services\TelegramApi;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'token', 'telegram_bot_id', 'username', 'first_name', 'webhook_secret', 'webhook_set', 'last_update_id', 'is_active'])]
#[Hidden(['token', 'webhook_secret'])]
class Bot extends Model
{
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'webhook_set' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(BotReply::class)->orderBy('sort_order')->orderBy('id');
    }

    public function telegramUsers(): HasMany
    {
        return $this->hasMany(TelegramUser::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    public function api(): TelegramApi
    {
        return new TelegramApi($this->token);
    }

    public function webhookUrl(): string
    {
        return rtrim(config('app.url'), '/').'/telegram/webhook/'.$this->id;
    }
}
