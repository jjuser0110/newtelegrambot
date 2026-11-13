<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\UpdateHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Receives updates from Telegram: POST /telegram/webhook/{bot}
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, Bot $bot, UpdateHandler $handler)
    {
        $secret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');
        if (! hash_equals($bot->webhook_secret, $secret)) {
            abort(403);
        }

        try {
            $handler->handle($bot, $request->all(), autoReply: $bot->is_active && $bot->user->is_active);
        } catch (Throwable $e) {
            // Always answer 200 so Telegram does not keep retrying the same update.
            Log::error('Telegram webhook error (bot '.$bot->id.'): '.$e->getMessage(), ['update' => $request->all()]);
        }

        return response()->json(['ok' => true]);
    }
}
