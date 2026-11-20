<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the logged-in user's language, or the language picked on the login page (session).
 */
class SetLocale
{
    /**
     * Supported panel languages (code => name shown in the switcher).
     *
     * @var array<string, string>
     */
    public const LOCALES = [
        'en' => 'English',
        'zh_CN' => '中文',
        'ms' => 'Bahasa Melayu',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale');

        if (! array_key_exists((string) $locale, self::LOCALES)) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);
        Carbon::setLocale($locale);

        return $next($request);
    }
}
