<?php

namespace App\Services;

use RuntimeException;

class TelegramException extends RuntimeException
{
    /**
     * Seconds Telegram asks us to wait (only set for 429 Too Many Requests).
     */
    public ?int $retryAfter = null;
}
