<?php

namespace App\Http\Controllers;

use App\Models\Bot;

abstract class Controller
{
    /**
     * Make sure the bot belongs to the logged in company. The superadmin can access every bot.
     */
    protected function ownBot(Bot $bot): Bot
    {
        $user = auth()->user();

        abort_unless($user->isSuperadmin() || $bot->user_id === $user->id, 404);

        return $bot;
    }
}
