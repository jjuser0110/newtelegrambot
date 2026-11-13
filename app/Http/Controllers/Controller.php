<?php

namespace App\Http\Controllers;

use App\Models\Bot;

abstract class Controller
{
    /** Make sure the bot belongs to the logged in company. */
    protected function ownBot(Bot $bot): Bot
    {
        abort_unless($bot->user_id === auth()->id(), 404);

        return $bot;
    }
}
