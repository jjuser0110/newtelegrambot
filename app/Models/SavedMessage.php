<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bot_id', 'label', 'from_chat_id', 'message_id', 'preview', 'has_custom_emoji'])]
class SavedMessage extends Model
{
    protected function casts(): array
    {
        return [
            'has_custom_emoji' => 'boolean',
        ];
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
