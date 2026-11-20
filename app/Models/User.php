<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'password', 'role', 'bot_limit', 'is_active', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_COMPANY = 'company';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'bot_limit' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function canAddBot(): bool
    {
        return $this->bots()->count() < $this->bot_limit;
    }
}
