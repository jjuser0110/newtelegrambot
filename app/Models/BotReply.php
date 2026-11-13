<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bot_id', 'trigger', 'message', 'image_path', 'buttons', 'sort_order', 'is_active'])]
class BotReply extends Model
{
    protected function casts(): array
    {
        return [
            'buttons' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
